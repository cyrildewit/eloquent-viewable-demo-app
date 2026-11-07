<?php

namespace App\Console\Commands;

use App\Demo\SimulatedVisitor;
use App\Demo\TrafficSimulator;
use App\Models\Episode;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use SplMinHeap;
use Throwable;

/**
 * Sends simulated traffic through the package's own recording path: the
 * guards, the dimension resolver, presence, and the queue or the Redis
 * buffer. demo:reset writes rows straight into the table instead, which is
 * fast but skips all of that.
 */
class SimulateTraffic extends Command
{
    protected $signature = 'demo:traffic
        {mode=live : live records in real time, replay compresses a past period}
        {--rate=20 : live: view attempts per second}
        {--duration=10m : live: how long to run, such as 90s, 10m or 2h}
        {--days=30 : replay: how many days back from now to fill}
        {--views=20000 : replay: how many views to record}
        {--via=sync : sync records during the call, queue hands every view to Horizon}
        {--store= : database or redis, instead of recording.store.driver, for this process only}
        {--max-backlog=5000 : with --via=queue, pause while more jobs than this wait}';

    protected $description = 'Record simulated traffic through the guards and the queue or Redis buffer';

    /** User agents per device, so the Device dimension resolves them back. */
    private const array AGENTS = [
        'desktop' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
            'Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15',
        ],
        'mobile' => [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36',
        ],
        'tablet' => [
            'Mozilla/5.0 (iPad; CPU OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',
        ],
    ];

    private bool $stop = false;

    private bool $queue = false;

    /** @var array<string, int> */
    private array $tally = ['attempted' => 0, 'recorded' => 0, 'queued' => 0, 'failed' => 0];

    /** @var array<string, int> */
    private array $skipped = [];

    /** @var array{post: array<int, Post>, episode: array<int, Episode>} */
    private array $models;

    /** @var array<int, User> */
    private array $users;

    public function handle(TrafficSimulator $traffic): int
    {
        $mode = $this->argument('mode');

        if (! in_array($mode, ['live', 'replay'], true) || ! in_array($this->option('via'), ['sync', 'queue'], true)) {
            $this->components->error('Use `live` or `replay`, with --via=sync or --via=queue.');

            return self::INVALID;
        }

        $configured = config('eloquent-viewable.recording.store.driver');
        $store = $this->option('store') ?: $configured;

        if (! in_array($store, ['database', 'redis'], true)) {
            $this->components->error('--store takes database or redis.');

            return self::INVALID;
        }

        // Before anything resolves the package's store or guards.
        config(['eloquent-viewable.recording.store.driver' => $store]);
        $this->queue = $this->option('via') === 'queue';

        if ($this->queue && $store === 'redis') {
            $this->components->warn('Queueing in front of the Redis buffer gains nothing: the buffer already moves the insert out of the call. Running it anyway.');
        }

        $this->models = [
            'post' => Post::query()->get()->keyBy('id')->all(),
            'episode' => Episode::query()->get()->keyBy('id')->all(),
        ];
        $this->users = User::query()->get()->keyBy('id')->all();

        $this->trap([SIGINT, SIGTERM], function (): void {
            $this->stop = true;
        });

        $started = microtime(true);

        $result = $mode === 'live' ? $this->live($traffic) : $this->replay($traffic);

        $this->summary($store, $configured, microtime(true) - $started, $mode);

        return $result;
    }

    private function live(TrafficSimulator $traffic): int
    {
        $rate = max(1, (int) $this->option('rate'));
        $seconds = Duration::tryParse((string) $this->option('duration'))?->toInterval()->totalSeconds ?? 600;
        $until = time() + (int) $seconds;

        $this->components->info(sprintf(
            'Recording about %d views a second for %s %s. Press Ctrl+C to stop.',
            $rate,
            $this->option('duration'),
            $this->queue ? 'through the queue, watch /horizon' : 'during each call',
        ));

        // Later views of a visit wait here until their time comes.
        $pending = new SplMinHeap;
        $sequence = 0;
        $lastReport = time();

        while (! $this->stop && time() < $until) {
            $tick = microtime(true);

            // Later pages of earlier visits come due first; new visits top
            // the second up to the rate. Each new visit records its first
            // view now and keeps the rest for later.
            $recorded = $this->drain($pending);

            for ($round = 0; $recorded < $rate && $round < 10 && ! $this->stop; $round++) {
                $traffic->live($rate - $recorded, function (array $view) use ($pending, &$sequence): void {
                    $pending->insert([$view['ts'], $sequence++, $view]);
                });

                $recorded += $this->drain($pending);
            }

            if (time() - $lastReport >= 5) {
                $lastReport = time();
                $this->line(sprintf(
                    '  %s  %s attempted · %s recorded · %s skipped · %s waiting in visits%s',
                    date('H:i:s'),
                    number_format($this->tally['attempted']),
                    number_format($this->tally['recorded']),
                    number_format(array_sum($this->skipped)),
                    number_format($pending->count()),
                    $this->backlogLabel(),
                ));
            }

            usleep((int) max(0, 1_000_000 - (microtime(true) - $tick) * 1_000_000));
        }

        if (! $pending->isEmpty()) {
            $this->components->twoColumnDetail('Later views of open visits, not recorded', number_format($pending->count()));
        }

        return self::SUCCESS;
    }

    /**
     * Records every pending view whose time has come.
     */
    private function drain(SplMinHeap $pending): int
    {
        $recorded = 0;

        while (! $pending->isEmpty() && $pending->top()[0] <= time() && ! $this->stop) {
            $this->record($pending->extract()[2]);
            $recorded++;
        }

        return $recorded;
    }

    private function replay(TrafficSimulator $traffic): int
    {
        $views = max(1, (int) $this->option('views'));
        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $this->option('days')))->startOfDay();

        // Views before the prune watermark would be invisible: the rollup
        // source reads that range from the tiers, which never fold them.
        $pruned = DB::table('view_state')->where('name', 'pruned')->value('value');

        if ($pruned !== null && $from->lt(CarbonImmutable::parse($pruned))) {
            $from = CarbonImmutable::parse($pruned)->addDay()->startOfDay();
            $this->components->warn("Views before {$pruned} were pruned, so the replay starts at {$from->toDateString()}.");
        }

        // Years squeezed into minutes would trip every guard that keeps state
        // in the cache, whose keys expire in real time. They stay on in live
        // mode, where time passes as it should.
        config([
            'eloquent-viewable.recording.guards' => array_values(array_diff(
                config('eloquent-viewable.recording.guards'),
                [EnforceCooldown::class, IgnoreBursts::class, ThrottleVisitors::class],
            )),
            'eloquent-viewable.presence.enabled' => false,
        ]);

        $this->components->info(sprintf(
            'Replaying about %s views from %s to now %s. EnforceCooldown, IgnoreBursts, ThrottleVisitors and presence are off for this run.',
            number_format($views),
            $from->toDateString(),
            $this->queue ? 'through the queue, watch /horizon' : 'during each call',
        ));

        $bar = $this->output->createProgressBar($views);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %message%');
        $bar->setMessage('');

        try {
            $traffic->replay($from, $to, $views, function (array $view) use ($bar): void {
                if ($this->stop) {
                    return;
                }

                Carbon::setTestNow(Carbon::createFromTimestamp($view['ts']));
                $this->record($view);
                $bar->setMessage(date('Y-m-d', $view['ts']).$this->backlogLabel());
                $bar->advance();
            });
        } finally {
            Carbon::setTestNow();
        }

        $bar->finish();
        $this->newLine(2);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $view
     */
    private function record(array $view): void
    {
        $model = $this->models[$view['type']][$view['id']] ?? null;

        if ($model === null) {
            return;
        }

        $viewer = $view['user'] !== null ? ($this->users[$view['user']] ?? null) : null;
        $hash = crc32($view['visitor']);
        $agents = self::AGENTS[$view['device']] ?? self::AGENTS['desktop'];

        $visitor = new SimulatedVisitor(
            id: $view['visitor'],
            userAgent: $agents[$hash % count($agents)],
            // One stable address per visitor, spread over many /24 networks
            // in the shared address space, so the burst guard's network
            // count sees households rather than one office.
            ip: sprintf('100.%d.%d.%d', 64 + $hash % 64, intdiv($hash, 64) % 256, intdiv($hash, 16_384) % 254 + 1),
            viewer: $viewer,
        );

        [$name, , $host] = $view['source'];

        $arrival = match (true) {
            $name === 'Newsletter' => new Arrival(null, array_filter(['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => $view['campaign']])),
            $host === null => new Arrival(null, []),
            default => new Arrival($host, []),
        };

        // The Country dimension reads the header Cloudflare would send.
        request()->headers->set('CF-IPCountry', $view['country']);

        $views = views($model)
            ->useVisitor($visitor)
            ->viewedBy($viewer)
            ->arrivedFrom($arrival)
            ->collection($view['collection'])
            ->cooldown(10);

        if ($this->queue) {
            $views->queue();
        }

        $this->tally['attempted']++;

        try {
            $result = $views->attempt();
        } catch (Throwable $e) {
            $this->tally['failed']++;
            report($e);

            return;
        }

        if ($result->skippedBy !== null) {
            $guard = class_basename($result->skippedBy);
            $this->skipped[$guard] = ($this->skipped[$guard] ?? 0) + 1;
        } elseif ($result->queued) {
            $this->tally['queued']++;
        } else {
            $this->tally['recorded']++;
        }

        if ($this->queue && $this->tally['attempted'] % 250 === 0) {
            $this->waitForBacklog();
        }
    }

    /**
     * Dispatching is far faster than Horizon works, so hold off while the
     * queue is full instead of piling a million jobs into Redis.
     */
    private function waitForBacklog(): void
    {
        $limit = max(100, (int) $this->option('max-backlog'));

        while (! $this->stop && Queue::size() > $limit) {
            sleep(1);
        }
    }

    private function backlogLabel(): string
    {
        $parts = [];

        if ($this->queue) {
            $parts[] = number_format((int) rescue(fn () => Queue::size(), 0, false)).' jobs queued';
        }

        if (config('eloquent-viewable.recording.store.driver') === 'redis') {
            $parts[] = number_format($this->streamLength()).' in the Redis stream';
        }

        return $parts === [] ? '' : ' · '.implode(' · ', $parts);
    }

    private function streamLength(): int
    {
        return (int) rescue(fn () => Redis::connection(config('eloquent-viewable.recording.store.redis.connection'))
            ->command('xlen', [config('eloquent-viewable.recording.store.redis.stream')]), 0, false);
    }

    private function summary(string $store, string $configured, float $seconds, string $mode): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Store</>', $store.($store !== $configured ? " (this run only, the app uses {$configured})" : ''));
        $this->components->twoColumnDetail('<fg=gray>Via</>', $this->queue ? 'queue' : 'sync');
        $this->components->twoColumnDetail('Attempted', number_format($this->tally['attempted']));
        $this->components->twoColumnDetail('Recorded', number_format($this->tally['recorded']));
        $this->components->twoColumnDetail('Queued', number_format($this->tally['queued']));

        foreach ($this->skipped as $guard => $count) {
            $this->components->twoColumnDetail("Skipped by {$guard}", number_format($count));
        }

        if ($this->tally['failed'] > 0) {
            $this->components->twoColumnDetail('<fg=red>Failed, see the log</>', number_format($this->tally['failed']));
        }

        $this->components->twoColumnDetail('Attempts per second', number_format($this->tally['attempted'] / max(0.001, $seconds), 1));

        if ($store === 'redis') {
            if ($configured !== 'redis') {
                $this->components->info('The scheduler only flushes when the app uses the redis store, so this run flushes its own buffer.');
                $this->call('views:flush');
            } else {
                $this->components->twoColumnDetail('In the Redis stream, flushed every minute by the scheduler', number_format($this->streamLength()));
            }
        }

        if ($mode === 'replay') {
            $this->components->bulletList([
                'Run views:rollup or views:maintain: buckets that were folded already are folded again with the late views.',
                'Run views:recount and views:pairs for the counter columns and "also viewed".',
            ]);
        }
    }
}
