<?php

namespace App\Console\Commands;

use App\Demo\SimulatedVisitor;
use App\Models\Post;
use App\Models\User;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Times the package's calls against whatever is in the database, without
 * `remember()`, so every number is what a cold page would pay. Each call runs
 * twice: the first run may read from disk, the second from the buffer pool.
 *
 * The results are saved, so two runs can be compared, and so can their
 * answers: the counts that do not depend on visitors must come out the same
 * after views:maintain folds and prunes.
 */
class BenchmarkViews extends Command
{
    protected $signature = 'demo:benchmark
        {label=run : A name for this run, used for the saved file}
        {--compare= : The label of an earlier run to compare timings and answers with}
        {--only= : Only the calls whose name contains this}
        {--recordings=200 : How many views to record for the recording timings}';

    protected $description = 'Time the package\'s reads and recording on the current data';

    private int $queries = 0;

    public function handle(): int
    {
        DB::listen(function (QueryExecuted $query): void {
            $this->queries++;
        });

        $post = Post::query()->where('slug', 'queues-in-laravel-from-first-job-to-horizon')->firstOrFail();
        $viral = Post::query()->where('slug', 'breaking-the-php-9-rfc-passes')->firstOrFail();
        $reader = User::query()->where('email', 'chloe@inkwell.test')->firstOrFail();
        $page = Post::query()->published()->latest('published_at')->limit(9)->get();
        $lastHour = Period::create(now()->subHour()->startOfHour(), now()->startOfHour());

        $calls = [
            // One article, a popular evergreen one.
            'post count() all time' => fn () => views($post)->count(),
            'post unique() all time' => fn () => views($post)->unique()->count(),
            'post count() 30 days' => fn () => views($post)->period(Period::pastDays(30))->count(),
            'post unique() 30 days' => fn () => views($post)->period(Period::pastDays(30))->unique()->count(),
            'post compare() 7 days' => fn () => views($post)->period(Period::pastDays(7))->compare()->toArray(),
            'post series 30 days by day' => fn () => views($post)->period(Period::pastDays(30))->countByInterval(Granularity::Day)->values(),
            'post series 2 years by month' => fn () => views($post)->period(Period::pastYears(2))->countByInterval(Granularity::Month)->values(),
            'post countBy(source) 1 year' => fn () => views($post)->period(Period::pastYears(1))->countBy('source', limit: 5)->toArray(),
            'post countByFrequency() 30 days' => fn () => views($post)->period(Period::pastDays(30))->countByFrequency()->toArray(),
            'post alsoViewed() all time' => fn () => views($post)->alsoViewed(5)->toArray(),
            'post alsoViewed() 30 days' => fn () => views($post)->period(Period::pastDays(30))->alsoViewed(5)->toArray(),

            // The viral article: a million views in the last day.
            'viral count() all time' => fn () => views($viral)->count(),
            'viral series 2 days by hour' => fn () => views($viral)->period(Period::subHours(48))->countByInterval(Granularity::Hour)->values(),

            // Every article together.
            'type count() 2 years' => fn () => views(Post::class)->period(Period::pastYears(2))->count(),
            'type count() 7 days' => fn () => views(Post::class)->period(Period::pastDays(7))->count(),
            'type unique() 30 days' => fn () => views(Post::class)->period(Period::pastDays(30))->unique()->count(),
            'type returning() 30 days' => fn () => views(Post::class)->period(Period::pastDays(30))->returning()->count(),
            'type series 30 days by day' => fn () => views(Post::class)->period(Period::pastDays(30))->countByInterval(Granularity::Day)->values(),
            'type series 2 years by month' => fn () => views(Post::class)->period(Period::pastYears(2))->countByInterval(Granularity::Month)->values(),
            'type countBy(source) 1 year' => fn () => views(Post::class)->period(Period::pastYears(1))->countBy('source', limit: 6)->toArray(),
            'type countBy(plan) 1 year' => fn () => views(Post::class)->period(Period::pastYears(1))->countBy('plan')->toArray(),
            'type two dimensions 30 days' => fn () => views(Post::class)->period(Period::pastDays(30))->whereDimension('source', 'Google')->whereDimension('device', 'mobile')->count(),
            'forViewables() 9 posts 7 days' => fn () => Views::forViewables($page)->period(Period::pastDays(7))->counts()->all(),

            // Rankings.
            'top() 7 days, every type' => fn () => $this->ranked(Views::period(Period::pastDays(7))->top(10)),
            'top() 2 years, articles' => fn () => $this->ranked(views(Post::class)->period(Period::pastYears(2))->top(10)),
            'trending()' => fn () => $this->ranked(views(Post::class)->trending(10)),
            'rising() 1 day' => fn () => $this->ranked(views(Post::class)->period(Period::pastDays(1))->rising(10)),
            'anomalies() last hour' => fn () => $this->ranked(views(Post::class)->period($lastHour)->anomalies(minimum: 5)),

            // Scopes on the posts table.
            'orderByViews() 7 days' => fn () => Post::query()->orderByViews('desc', Period::pastDays(7))->limit(10)->pluck('id')->all(),
            'orderByTrending()' => fn () => Post::query()->orderByTrending()->limit(10)->pluck('id')->all(),
            'whereViewsCount() >= 1000' => fn () => Post::query()->whereViewsCount('>=', 1_000)->count(),
            'orderBy counter column' => fn () => Post::query()->orderByDesc('views_last_week')->limit(10)->pluck('id')->all(),

            // One reader.
            'recommended() 30 days' => fn () => views(Post::class)->viewedBy($reader)->period(Period::pastDays(30))->recommended(5)->toArray(),
            'whereNotViewedBy()' => fn () => Post::query()->whereNotViewedBy($reader)->count(),
            'viewed() history page' => fn () => $reader->viewed()->limit(12)->get()->count(),
        ];

        if ($only = $this->option('only')) {
            $calls = array_filter($calls, fn (string $name): bool => str_contains($name, $only), ARRAY_FILTER_USE_KEY);
        }

        Views::flushCache();

        $results = [];

        foreach ($calls as $name => $call) {
            $results[$name] = $this->time($call);
            $this->components->twoColumnDetail($name, $this->line_($results[$name]));
        }

        $results += $this->recording(Post::query()->whereNull('published_at')->firstOrFail());

        $this->save($results);
        $this->compare($results);

        return self::SUCCESS;
    }

    /**
     * @return array{first: float, repeat: float, queries: int, answer: string|null, error: string|null}
     */
    private function time(\Closure $call): array
    {
        $timings = [];
        $answer = null;

        try {
            foreach ([1, 2] as $run) {
                $this->queries = 0;
                $started = hrtime(true);
                $value = $call();
                $timings[] = (hrtime(true) - $started) / 1e6;
                $queries = $this->queries;
            }

            $answer = json_encode($value);
        } catch (Throwable $e) {
            return ['first' => 0, 'repeat' => 0, 'queries' => 0, 'answer' => null, 'error' => class_basename($e).': '.Str::limit($e->getMessage(), 120)];
        }

        return ['first' => round($timings[0], 1), 'repeat' => round($timings[1], 1), 'queries' => $queries, 'answer' => $answer, 'error' => null];
    }

    /**
     * Recording through every guard and dimension, as a request would, with
     * fresh visitors so no cooldown or burst refuses them. They go to the
     * unpublished draft and are deleted afterwards, so they never change the
     * answers two runs compare.
     *
     * @return array<string, array<string, mixed>>
     */
    private function recording(Post $post): array
    {
        $count = max(1, (int) $this->option('recordings'));
        $results = [];

        foreach (['record() sync, per view' => false, 'record() queued, per view' => true] as $name => $queue) {
            $started = hrtime(true);
            $this->queries = 0;

            for ($i = 0; $i < $count; $i++) {
                $visitor = new SimulatedVisitor('bench-'.Str::random(30), ip: '100.'.(64 + $i % 60).'.'.($i % 250).'.'.($i % 200 + 1));
                views($post)->useVisitor($visitor)->queue($queue)->record();
            }

            $perView = (hrtime(true) - $started) / 1e6 / $count;
            $results[$name] = ['first' => round($perView, 2), 'repeat' => round($perView, 2), 'queries' => (int) round($this->queries / $count), 'answer' => null, 'error' => null];
            $this->components->twoColumnDetail($name, $this->line_($results[$name]));
        }

        // Let Horizon write the queued ones, then take every benchmark view out.
        for ($wait = 0; $wait < 60 && Queue::size() > 0; $wait++) {
            sleep(1);
        }

        DB::table('views')->where('viewable_type', $post->getMorphClass())->where('viewable_id', $post->getKey())->where('visitor', 'like', 'bench-%')->delete();
        Views::flushCache();

        return $results;
    }

    private function ranked(iterable $ranking): array
    {
        $entries = [];

        foreach ($ranking as $entry) {
            $entries[] = [$entry->viewable->getKey(), $entry->count];
        }

        return $entries;
    }

    private function line_(array $result): string
    {
        if ($result['error'] !== null) {
            return '<fg=yellow>'.$result['error'].'</>';
        }

        $color = $result['first'] >= 1_000 ? 'red' : ($result['first'] >= 100 ? 'yellow' : 'green');

        return sprintf('<fg=%s>%s ms</> · repeat %s ms · %d %s', $color, number_format($result['first'], 1), number_format($result['repeat'], 1), $result['queries'], Str::plural('query', $result['queries']));
    }

    private function save(array $results): void
    {
        $directory = storage_path('app/benchmarks');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents("{$directory}/{$this->argument('label')}.json", json_encode([
            'label' => $this->argument('label'),
            'at' => now()->toIso8601String(),
            'views' => DB::table('views')->count(),
            'rollup_rows' => DB::table(config('eloquent-viewable.retention.rollups.table'))->count(),
            'results' => $results,
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->components->info("Saved to storage/app/benchmarks/{$this->argument('label')}.json");
    }

    private function compare(array $results): void
    {
        $label = $this->option('compare');

        if ($label === null) {
            return;
        }

        $file = storage_path("app/benchmarks/{$label}.json");

        if (! is_file($file)) {
            $this->components->error("No saved run called {$label}.");

            return;
        }

        $before = json_decode((string) file_get_contents($file), true);
        $rows = [];

        foreach ($results as $name => $after) {
            $old = $before['results'][$name] ?? null;

            if ($old === null) {
                continue;
            }

            $same = match (true) {
                $old['answer'] === null || $after['answer'] === null => '',
                $old['answer'] === $after['answer'] => 'same',
                default => 'differs',
            };

            $rows[] = [$name, number_format($old['first'], 1), number_format($after['first'], 1), $old['first'] > 0 ? round($old['first'] / max(0.1, $after['first']), 1).'x' : '', $same];
        }

        $this->newLine();
        $this->table(["call, compared with {$label}", 'before ms', 'now ms', 'faster', 'answer'], $rows);
    }
}
