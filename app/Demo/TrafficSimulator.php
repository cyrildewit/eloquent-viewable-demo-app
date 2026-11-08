<?php

namespace App\Demo;

use App\Models\Episode;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use CyrildeWit\EloquentViewable\Facades\Views;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes made-up traffic straight into the views table, the way the package
 * would have recorded it: a visitor id, the viewer when signed in, a
 * collection, the dimension columns and `context->plan`.
 *
 * A visit is one visitor reading one to four pieces, mostly on the topics
 * they like, so "also viewed" and "recommended for you" have real co-viewing
 * to work with. Traffic follows the hour of the day and the day of the week,
 * new posts get a head start, and the site grows over time.
 */
final class TrafficSimulator
{
    /** @var list<array{0: string, 1: string, 2: ?string, 3: int}> name, medium, referrer host, weight */
    private const array SOURCES = [
        ['Google', 'organic', 'google.com', 34],
        ['Direct', 'direct', null, 16],
        ['Newsletter', 'email', null, 9],
        ['Hacker News', 'referral', 'news.ycombinator.com', 6],
        ['Reddit', 'social', 'reddit.com', 5],
        ['X', 'social', 'x.com', 4],
        ['LinkedIn', 'social', 'linkedin.com', 3],
        ['Bing', 'organic', 'bing.com', 4],
        ['DuckDuckGo', 'organic', 'duckduckgo.com', 4],
        ['ChatGPT', 'referral', 'chatgpt.com', 3],
        ['Laravel News', 'referral', 'laravel-news.com', 4],
        ['Bluesky', 'social', 'bsky.app', 3],
    ];

    private const array COUNTRIES = ['US' => 28, 'DE' => 10, 'NL' => 9, 'GB' => 9, 'IN' => 8, 'FR' => 6, 'BR' => 5, 'CA' => 5, 'PL' => 4, 'ES' => 4, 'JP' => 3, 'AU' => 3, 'SE' => 2, 'NG' => 2];

    private const array DEVICES = ['desktop' => 55, 'mobile' => 38, 'tablet' => 7];

    private const array HOURS = [0.35, 0.25, 0.2, 0.2, 0.25, 0.35, 0.55, 0.85, 1.2, 1.5, 1.6, 1.6, 1.45, 1.5, 1.6, 1.55, 1.4, 1.2, 1.0, 0.95, 0.9, 0.8, 0.65, 0.5];

    /** Rows per insert statement: about a megabyte of SQL, far below max_allowed_packet. */
    private const int Batch = 5_000;

    private const array COLUMNS = ['viewable_type', 'viewable_id', 'viewer_type', 'viewer_id', 'visitor', 'collection', 'context', 'viewed_at', 'source', 'medium', 'campaign', 'device', 'country', 'referrer_host'];

    /** @var list<string> value tuples waiting for the next insert */
    private array $rows = [];

    private ?\PDO $pdo = null;

    /** How much each type weighs when a visitor picks what to read. */
    private array $typeWeights = ['post' => 1.0, 'episode' => 0.6];

    /** @var array<int, float> the visitors active on `$activeDay`, by index, with their weight */
    private array $active = [];

    private ?int $activeDay = null;

    /** @var array<int, list<int>> */
    private array $startsOn = [];

    /** @var array<int, list<int>> */
    private array $endsOn = [];

    private int $written = 0;

    /** @var list<array{type: string, id: int, topic: string, published: int, pop: float, author: ?int}> */
    private array $items = [];

    /** @var list<array<string, mixed>> */
    private array $visitors = [];

    /**
     * When set, every generated view goes here instead of into the views
     * table, so demo:traffic can record it through the package.
     *
     * @var ?Closure(array<string, mixed>): void
     */
    private ?Closure $sink = null;

    /**
     * Whether the later views of a visit may lie in the future, for live
     * traffic that plays them out as the clock gets there.
     */
    private bool $future = false;

    /**
     * Puts most of the traffic on one type, such as a million article views
     * next to a few thousand plays.
     */
    public function focus(?string $type): self
    {
        $this->typeWeights = match ($type) {
            'post' => ['post' => 1.0, 'episode' => 0.04],
            'episode' => ['post' => 0.08, 'episode' => 1.0],
            default => ['post' => 1.0, 'episode' => 0.6],
        };

        return $this;
    }

    /**
     * About `$views` views over the last `$days` days, about a hundred
     * thousand over two years by default. The site grows over time, so the
     * recent months hold more of them.
     *
     * @param  ?Closure(int, int, int): void  $progress  called after each day with the day, the number of days and the views written
     */
    public function history(int $days = 730, int $views = 110_000, ?Closure $progress = null): int
    {
        $this->load();

        // One visitor per thirty views keeps the uniques and returning
        // visitors plausible, capped so a long run stays fast to generate.
        $this->makeVisitors($days, max(2_000, min(250_000, intdiv($views, 30))));

        $now = CarbonImmutable::now();
        $start = $now->subDays($days)->startOfDay();
        $weights = [];

        // Days before the first piece was published have nothing to read, so
        // their share goes to the days that do.
        $firstPublished = min(array_column($this->items, 'published') ?: [$now->getTimestamp()]);

        for ($d = 0; $d <= $days; $d++) {
            $day = $start->addDays($d);
            $weights[$d] = $day->endOfDay()->getTimestamp() < $firstPublished
                ? 0
                : $this->dayWeight($day, $days - $d, $days) * ($day->isSameDay($now) ? $now->secondsSinceMidnight() / 86_400 : 1);
        }

        $scale = $views / max(1, array_sum($weights));

        for ($d = 0; $d <= $days; $d++) {
            $day = $start->addDays($d);

            $this->day($day, (int) round($weights[$d] * $scale * $this->between(0.9, 1.1)), $d, $days, fn (): int => $day->getTimestamp() + $this->hour() * 3_600 + mt_rand(0, 3_599));

            if ($progress !== null) {
                $progress($d, $days, $this->written + count($this->rows));
            }
        }

        return $this->finish();
    }

    /**
     * A post goes viral: `$views` views within `$days` days of it being
     * published, or of `$days` ago for an older post, from mostly new
     * visitors arriving from Hacker News, Reddit and social media. Traffic
     * peaks at the start and halves every 18 hours.
     *
     * @param  ?Closure(int): void  $progress  called every 100,000 views with the views written
     */
    public function viral(Post $post, int $views, int $days = 5, ?Closure $progress = null): int
    {
        $end = time();
        $start = max($post->published_at->getTimestamp(), $end - $days * 86_400);
        $hours = max(1, intdiv($end - $start, 3_600) + 1);

        $weights = [];

        for ($h = 0; $h < $hours; $h++) {
            $weights[] = 0.5 ** ($h / 18) * self::HOURS[(int) date('G', $start + $h * 3_600)];
        }

        $table = $this->cumulative($weights);
        $sources = [
            ['Hacker News', 'referral', 'news.ycombinator.com', 30], ['Reddit', 'social', 'reddit.com', 15], ['X', 'social', 'x.com', 15],
            ['Direct', 'direct', null, 15], ['Google', 'organic', 'google.com', 12], ['LinkedIn', 'social', 'linkedin.com', 5],
            ['Bluesky', 'social', 'bsky.app', 5], ['ChatGPT', 'referral', 'chatgpt.com', 3],
        ];
        $sourceWeights = array_column($sources, 3);
        $devices = ['mobile' => 55, 'desktop' => 38, 'tablet' => 7];

        // A few readers come back to it, most are one-off.
        $returning = [];

        for ($i = 0; $i < 20_000; $i++) {
            $returning[] = 'viral-'.Str::random(34);
        }

        for ($i = 0; $i < $views; $i++) {
            $ts = min($end, $start + $this->pick($table) * 3_600 + mt_rand(0, 3_599));
            $visitor = mt_rand(1, 100) <= 15 ? $returning[array_rand($returning)] : Str::random(40);

            $this->push('post', $post->id, null, $visitor, null, ['plan' => 'guest'], $ts, $sources[$this->weighted($sourceWeights)], null, $this->weighted($devices), $this->weighted(self::COUNTRIES));

            if ($progress !== null && $i % 100_000 === 0) {
                $progress($this->written);
            }
        }

        return $this->finish();
    }

    /**
     * Hands about `$views` views between `$from` and `$to` to `$sink`, in the
     * order they happen, with the same daily rhythm and growth as the seeded
     * history. Nothing is written: the sink decides how to record them.
     *
     * @param  Closure(array<string, mixed>): void  $sink
     * @param  ?Closure(int, int): void  $progress  called after each day with the day and the number of days
     */
    public function replay(CarbonImmutable $from, CarbonImmutable $to, int $views, Closure $sink, ?Closure $progress = null): void
    {
        $this->load();

        $days = max(1, (int) ceil($from->diffInDays($to)));
        $this->makeVisitors($days, max(500, min(20_000, intdiv($views, 12))));

        $weights = [];

        for ($d = 0; $d <= $days; $d++) {
            $weights[$d] = $this->dayWeight($from->startOfDay()->addDays($d), $days - $d, $days);
        }

        $total = array_sum($weights);
        $start = $from->getTimestamp();
        $end = min($to->getTimestamp(), time());

        for ($d = 0; $d <= $days; $d++) {
            $day = $from->startOfDay()->addDays($d);
            $buffer = [];

            $this->sink = function (array $view) use (&$buffer): void {
                $buffer[] = $view;
            };

            $this->day($day, (int) round($views * $weights[$d] / $total), $d, $days, fn (): int => $day->getTimestamp() + $this->hour() * 3_600 + mt_rand(0, 3_599));

            $this->sink = null;

            usort($buffer, fn (array $a, array $b): int => $a['ts'] <=> $b['ts']);

            foreach ($buffer as $view) {
                if ($view['ts'] >= $start && $view['ts'] <= $end) {
                    $sink($view);
                }
            }

            if ($progress !== null) {
                $progress($d, $days);
            }
        }
    }

    /**
     * One tick of live traffic: about `$views` views of visits that start
     * now. The later views of a visit lie up to a few minutes ahead, so the
     * caller keeps them until their time comes.
     *
     * @param  Closure(array<string, mixed>): void  $sink
     */
    public function live(int $views, Closure $sink, int $rate = 20): void
    {
        $this->load();

        // Enough visitors that one rarely opens the same piece twice within
        // the ten minute cooldown: about ten minutes of traffic.
        if ($this->visitors === []) {
            $this->makeVisitors(30, max(3_000, $rate * 600));
        }

        $now = time();

        $this->future = true;
        $this->sink = $sink;

        try {
            $this->day(CarbonImmutable::now()->startOfDay(), $views, 30, 30, fn (): int => $now);
        } finally {
            $this->future = false;
            $this->sink = null;
        }
    }

    /**
     * Visits spread over the last few minutes, such as the past hour.
     */
    public function recent(int $views = 250, int $minutes = 60): int
    {
        $this->load();
        $this->makeVisitors(30, 900, recentOnly: true);

        $now = CarbonImmutable::now();
        $from = $now->subMinutes($minutes)->getTimestamp();

        $this->day($now->startOfDay(), $views, 30, 30, fn (): int => mt_rand($from, $now->getTimestamp()));

        return $this->finish();
    }

    /**
     * Hacker News finds a post: many new visitors within the last closed
     * hour, the window views:detect-spikes looks at.
     */
    public function spike(Post $post, int $views = 80): int
    {
        [$from, $until] = $this->lastClosedHour();

        for ($i = 0; $i < $views; $i++) {
            $this->push('post', $post->id, null, 'hn-'.Str::random(24), null, ['plan' => 'guest'], mt_rand($from, $until - 1), ['Hacker News', 'referral', 'news.ycombinator.com'], null, $this->weighted(self::DEVICES), $this->weighted(self::COUNTRIES));
        }

        return $this->finish();
    }

    /**
     * A post that used to get about twenty views at this hour every week gets
     * none in the last closed hour, as if a link to it broke.
     */
    public function drop(Post $post): int
    {
        [$from, $until] = $this->lastClosedHour();

        DB::table('views')
            ->where('viewable_type', $post->getMorphClass())
            ->where('viewable_id', $post->id)
            ->whereBetween('viewed_at', [date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $until - 1)])
            ->delete();

        for ($week = 1; $week <= 4; $week++) {
            $shift = $week * 7 * 86_400;

            for ($i = 0, $n = mt_rand(18, 24); $i < $n; $i++) {
                $this->push('post', $post->id, null, Str::random(40), null, ['plan' => 'guest'], mt_rand($from - $shift, $until - $shift - 1), self::SOURCES[0], null, $this->weighted(self::DEVICES), $this->weighted(self::COUNTRIES));
            }
        }

        return $this->finish();
    }

    /**
     * Enough new views to carry a post past its next `total_views` milestone
     * at the next recount.
     */
    public function milestone(Post $post): array
    {
        $count = views($post)->count();
        $thresholds = config('eloquent-viewable.milestones.thresholds.'.Post::class.'.total_views', []);
        $next = collect($thresholds)->first(fn (int $threshold): bool => $threshold > $count) ?? $count + 100;
        $needed = $next - $count + mt_rand(1, 9);

        $now = time();

        for ($i = 0; $i < $needed; $i++) {
            $this->push('post', $post->id, null, Str::random(40), null, ['plan' => 'guest'], mt_rand($now - 1_800, $now), self::SOURCES[0], null, $this->weighted(self::DEVICES), $this->weighted(self::COUNTRIES));
        }

        return ['views' => $this->finish(), 'milestone' => $next];
    }

    /**
     * A scraper that opens a burst of different pages within seconds, three
     * times, recorded as if the guard had not been there yet. views:purge-bots
     * finds it afterwards.
     */
    public function bots(): array
    {
        $this->load();

        $visitor = 'bot-'.Str::random(36);
        $start = time() - 3_600;

        for ($burst = 0; $burst < 3; $burst++) {
            $t = $start + $burst * 900;

            foreach (collect($this->items)->shuffle()->take(16) as $item) {
                $this->push($item['type'], $item['id'], null, $visitor, null, ['plan' => 'guest'], $t + mt_rand(0, 1), ['Direct', 'direct', null], null, 'desktop', 'US');
            }
        }

        return ['views' => $this->finish(), 'visitor' => $visitor];
    }

    /**
     * Readers who have a page open right now. They pass the guards like any
     * view, but only keep their visitor active in presence; nothing is
     * recorded.
     */
    public function liveReaders(int $readers = 24): int
    {
        $this->load();

        $users = User::query()->where('tracks_reading', true)->inRandomOrder()->limit(3)->get();
        $weights = [];

        foreach ($this->items as $i => $item) {
            $age = max(0, (time() - $item['published']) / 86_400);
            $weights[$i] = $item['pop'] * (1 + 8 * exp(-$age / 4));
        }

        $models = [
            'post' => Post::query()->get()->keyBy('id'),
            'episode' => Episode::query()->get()->keyBy('id'),
        ];

        $present = 0;

        for ($i = 0; $i < $readers; $i++) {
            $item = $this->items[$this->weighted($weights)];
            $viewer = $i < $users->count() ? $users[$i] : null;
            $agent = array_rand(array_intersect_key(SimulatedVisitor::AGENTS, array_flip(['chrome', 'iphone', 'ipad', 'android'])));

            $visitor = new SimulatedVisitor(
                id: 'live-'.Str::random(32),
                userAgent: SimulatedVisitor::agent($agent),
                ip: '203.0.113.'.mt_rand(1, 254),
                viewer: $viewer,
            );

            $model = $models[$item['type']][$item['id']];

            if (views($model)->useVisitor($visitor)->viewedBy($viewer)->heartbeat()) {
                $present++;
            }
        }

        return $present;
    }

    /**
     * @param  Closure(): int  $time
     */
    private function day(CarbonImmutable $day, int $views, int $d, int $days, Closure $time): void
    {
        $active = $this->activeOn($d);

        if ($active === []) {
            return;
        }

        $eligible = array_keys($active);
        $visitorTable = $this->cumulative(array_values($active));
        $itemTables = $this->itemTables($day->endOfDay()->getTimestamp(), $day->getTimestamp());

        if ($itemTables === null) {
            return;
        }

        $sourceWeights = [];

        foreach (self::SOURCES as $i => $source) {
            // AI assistants send more readers every month.
            $sourceWeights[$i] = $source[0] === 'ChatGPT' ? $source[3] * (0.2 + 4 * ($d / max(1, $days)) ** 2) : $source[3];
        }

        $made = 0;
        $guard = 0;
        $now = time();

        while ($made < $views && $guard++ < $views * 4) {
            $visitor = $this->visitors[$eligible[$this->pick($visitorTable)]];
            $ts = $time();

            if (! $this->future && $ts > $now) {
                continue;
            }

            $length = $this->weighted([1 => 48, 2 => 27, 3 => 15, 4 => 10]);
            $source = mt_rand(1, 100) <= 55 ? self::SOURCES[$visitor['source']] : self::SOURCES[$this->weighted($sourceWeights)];
            $campaign = $source[0] === 'Newsletter' ? $this->campaign($ts) : null;
            $seen = [];

            for ($j = 0; $j < $length && $made < $views; $j++) {
                $topic = $visitor['topics'][array_rand($visitor['topics'])];
                $item = null;

                for ($try = 0; $try < 6; $try++) {
                    $candidate = $this->items[$itemTables[$topic][1][$this->pick($itemTables[$topic][0])]];

                    if ($candidate['published'] > $ts || isset($seen[$candidate['type'].$candidate['id']])) {
                        continue;
                    }

                    if ($visitor['user'] !== null && $candidate['author'] === $visitor['user']) {
                        continue;
                    }

                    $item = $candidate;
                    break;
                }

                if ($item === null) {
                    break;
                }

                $seen[$item['type'].$item['id']] = true;

                $collection = null;

                if ($item['type'] === 'post') {
                    $roll = mt_rand(1, 100);
                    $collection = $roll <= 5 ? 'feed' : ($roll <= 8 ? 'amp' : null);
                }

                // Later pages of a visit come from the site itself, which the Source
                // dimension counts as Direct, unless the reader opened them in tabs
                // from the same referrer.
                $entry = $j === 0 || mt_rand(1, 100) <= 45 ? $source : self::SOURCES[1];

                $this->push($item['type'], $item['id'], $visitor['user'], $visitor['id'], $collection, ['plan' => $visitor['plan']], $ts, $entry, $j === 0 ? $campaign : null, $visitor['device'], $visitor['country']);
                $made++;

                if ($item['type'] === 'episode' && mt_rand(1, 100) <= 45) {
                    $this->push('episode', $item['id'], $visitor['user'], $visitor['id'], 'plays', ['plan' => $visitor['plan']], $ts + mt_rand(5, 90), self::SOURCES[1], null, $visitor['device'], $visitor['country']);
                    $made++;
                }

                $ts += mt_rand(45, 480);

                if (! $this->future && $ts > $now) {
                    break;
                }
            }
        }
    }

    /**
     * The visitors active on day `$d`. Walking the days in order only adds
     * the visitors who start that day and drops those who stopped the day
     * before, instead of looking at every visitor again.
     *
     * @return array<int, float>
     */
    private function activeOn(int $d): array
    {
        if ($this->activeDay === $d - 1) {
            foreach ($this->startsOn[$d] ?? [] as $i) {
                $this->active[$i] = $this->visitors[$i]['weight'];
            }

            foreach ($this->endsOn[$d - 1] ?? [] as $i) {
                unset($this->active[$i]);
            }
        } elseif ($this->activeDay !== $d) {
            $this->active = [];

            foreach ($this->visitors as $i => $visitor) {
                if ($visitor['from'] <= $d && $visitor['to'] >= $d) {
                    $this->active[$i] = $visitor['weight'];
                }
            }
        }

        $this->activeDay = $d;

        return $this->active;
    }

    /**
     * Per topic, the cumulative weights of the pieces published by the end of
     * the day: popular and fresh pieces, and pieces on the topic, weigh more.
     *
     * @return ?array<string, array{0: list<float>, 1: list<int>}>
     */
    private function itemTables(int $endOfDay, int $startOfDay): ?array
    {
        $tables = [];

        foreach (array_keys(Catalog::TOPICS) as $topic) {
            $weights = [];
            $indexes = [];

            foreach ($this->items as $i => $item) {
                if ($item['published'] > $endOfDay) {
                    continue;
                }

                $age = max(0, ($startOfDay - $item['published']) / 86_400);
                $fresh = 1 + 8 * exp(-$age / 4) + 2 * exp(-$age / 30);
                $weights[] = $item['pop'] * $fresh * ($item['topic'] === $topic ? 8 : 1) * $this->typeWeights[$item['type']];
                $indexes[] = $i;
            }

            if ($weights === []) {
                return null;
            }

            $tables[$topic] = [$this->cumulative($weights), $indexes];
        }

        return $tables;
    }

    private function load(): void
    {
        if ($this->items !== []) {
            return;
        }

        $pops = collect(Catalog::posts())->mapWithKeys(fn (array $post) => [Str::slug($post['title']) => $post['pop']])
            ->merge(collect(Catalog::episodes())->mapWithKeys(fn (array $episode) => ['ep-'.Str::slug($episode['title']) => $episode['pop']]));

        foreach (Post::query()->published()->get() as $post) {
            $this->items[] = ['type' => 'post', 'id' => $post->id, 'topic' => $post->topic, 'published' => $post->published_at->getTimestamp(), 'pop' => $pops[$post->slug] ?? 0.5, 'author' => $post->user_id];
        }

        foreach (Episode::query()->published()->get() as $episode) {
            $this->items[] = ['type' => 'episode', 'id' => $episode->id, 'topic' => $episode->topic, 'published' => $episode->published_at->getTimestamp(), 'pop' => $pops['ep-'.$episode->slug] ?? 0.5, 'author' => null];
        }
    }

    private function makeVisitors(int $days, int $count, bool $recentOnly = false): void
    {
        $topics = array_keys(Catalog::TOPICS);
        $this->visitors = [];

        // Signed-in readers browse a lot, so they have a history to recommend from.
        if (! $recentOnly) {
            $people = collect(Catalog::people())->keyBy('email');

            foreach (User::query()->get() as $user) {
                $person = $people[$user->email] ?? null;

                $this->visitors[] = [
                    'id' => 'seed-'.Str::random(35),
                    'topics' => $person['topics'] ?? $this->someTopics($topics),
                    'device' => $this->weighted(self::DEVICES),
                    'country' => $this->weighted(self::COUNTRIES),
                    'source' => $this->weighted(array_column(self::SOURCES, 3)),
                    'weight' => $person !== null ? 6.0 : 2.5,
                    'from' => match ($user->email) {
                        'eve@inkwell.test' => $days - 7,
                        default => 0,
                    },
                    'to' => match ($user->email) {
                        'dan@inkwell.test' => $days - 30,
                        default => $days,
                    },
                    'user' => $user->id,
                    'plan' => $user->plan,
                ];
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $loyal = mt_rand(1, 100) <= 18;
            $from = $loyal ? mt_rand(0, (int) ($days * 0.6)) : mt_rand(-60, $days);
            $length = $loyal ? $days : (int) max(1, -log(max(1e-6, mt_rand() / mt_getrandmax())) * 90);

            $this->visitors[] = [
                'id' => Str::random(40),
                'topics' => $this->someTopics($topics),
                'device' => $this->weighted(self::DEVICES),
                'country' => $this->weighted(self::COUNTRIES),
                'source' => $this->weighted(array_column(self::SOURCES, 3)),
                'weight' => exp($this->gauss() * 1.1) * ($loyal ? 2.0 : 1.0),
                'from' => $recentOnly ? 0 : $from,
                'to' => $recentOnly ? $days : $from + $length,
                'user' => null,
                'plan' => 'guest',
            ];
        }

        $this->active = [];
        $this->activeDay = null;
        $this->startsOn = [];
        $this->endsOn = [];

        foreach ($this->visitors as $i => $visitor) {
            $this->startsOn[$visitor['from']][] = $i;
            $this->endsOn[$visitor['to']][] = $i;
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: ?string}  $source
     */
    private function push(string $type, int $id, ?int $user, string $visitor, ?string $collection, array $context, int $ts, array $source, ?string $campaign, string $device, string $country): void
    {
        if ($this->sink !== null) {
            ($this->sink)([
                'type' => $type,
                'id' => $id,
                'user' => $user,
                'visitor' => $visitor,
                'collection' => $collection,
                'ts' => $ts,
                'source' => $source,
                'campaign' => $campaign,
                'device' => $device,
                'country' => $country,
            ]);

            return;
        }

        // Built as SQL straight away: binding 14 values per row would hit the
        // 65,535 placeholder limit at a few thousand rows, and preparing a
        // statement that large is slower than quoting.
        $pdo = $this->pdo ??= DB::connection()->getPdo();
        $text = fn (?string $value): string => $value === null ? 'NULL' : $pdo->quote($value);

        $this->rows[] = '('.$text($type).','.$id.','.($user === null ? 'NULL,NULL' : "'user',".$user).','
            .$text($visitor).','.$text($collection).','.$text(json_encode($context)).",'".date('Y-m-d H:i:s', $ts)."',"
            .$text($source[0]).','.$text($source[1]).','.$text($campaign).','.$text($device).','.$text($country).','.$text($source[2]).')';

        if (count($this->rows) >= self::Batch) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->rows === []) {
            return;
        }

        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $columns = implode(',', array_map($grammar->wrap(...), self::COLUMNS));

        $connection->unprepared("insert into {$grammar->wrapTable('views')} ({$columns}) values ".implode(',', $this->rows));

        $this->written += count($this->rows);
        $this->rows = [];
    }

    private function finish(): int
    {
        $this->flush();

        // Remembered counts would hide the new rows.
        Views::flushCache();

        $written = $this->written;
        $this->written = 0;

        return $written;
    }

    /**
     * How busy a day is: the site grows over time and is quieter at weekends.
     */
    private function dayWeight(CarbonImmutable $day, int $age, int $days): float
    {
        $base = $age <= 60 ? 430 : 70 + 120 * (1 - $age / max(1, $days));

        return $base * match ($day->dayOfWeekIso) {
            6 => 0.7,
            7 => 0.75,
            default => 1.15,
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function lastClosedHour(): array
    {
        $until = CarbonImmutable::now()->startOfHour();

        return [$until->subHour()->getTimestamp(), $until->getTimestamp()];
    }

    private function campaign(int $ts): string
    {
        $date = CarbonImmutable::createFromTimestamp($ts);

        return match (true) {
            $date->month === 12 && $date->day >= 15 => 'year-in-review',
            $date->month === 11 && $date->day >= 24 && $date->day <= 30 => 'black-friday',
            $date->month === 3 && $date->day <= 7 => 'launch-week',
            default => 'weekly-digest',
        };
    }

    private function someTopics(array $topics): array
    {
        shuffle($topics);

        return array_slice($topics, 0, mt_rand(1, 100) <= 60 ? 1 : 2);
    }

    private function hour(): int
    {
        return $this->weighted(self::HOURS);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, float|int>  $weights
     * @return TKey
     */
    private function weighted(array $weights): int|string
    {
        $total = array_sum($weights);
        $roll = mt_rand() / mt_getrandmax() * $total;

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @param  list<float|int>  $weights
     * @return list<float>
     */
    private function cumulative(array $weights): array
    {
        $sum = 0.0;
        $table = [];

        foreach ($weights as $weight) {
            $sum += $weight;
            $table[] = $sum;
        }

        return $table;
    }

    /**
     * @param  list<float>  $table
     */
    private function pick(array $table): int
    {
        $roll = mt_rand() / mt_getrandmax() * end($table);
        $low = 0;
        $high = count($table) - 1;

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if ($table[$mid] < $roll) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low;
    }

    private function between(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    private function gauss(): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
