<?php

namespace App\Console\Commands;

use App\Demo\Autopilot;
use App\Demo\Catalog;
use App\Demo\TrafficSimulator;
use App\Demo\ViewsIndexes;
use App\Models\DemoEvent;
use App\Models\Post;
use Database\Seeders\ContentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

/**
 * Rebuilds the database with simulated history, from a hundred thousand views
 * up to tens of millions. Rows go straight into the views table, the way the
 * package would have recorded them; `demo:traffic` records through the
 * package instead.
 */
class ResetDemo extends Command
{
    protected $signature = 'demo:reset
        {--views=110000 : How many views to simulate, such as 1000000 or 20000000}
        {--days=730 : Over how many days, ending now}
        {--focus= : post or episode, to put most of the views on one type}
        {--viral=* : A post that goes viral, as slug:views, such as breaking-the-php-9-rfc-passes:1000000}
        {--bulk : Drop the indexes during the load and build them afterwards; on by default from a million views}
        {--no-bulk : Keep the indexes during the load}
        {--skip-derived : Skip views:pairs and views:recount afterwards}
        {--force : Do not ask before a load of more than five million views}';

    protected $description = 'Rebuild the database with simulated traffic, up to tens of millions of views';

    /** Bytes per view on disk with the demo's indexes, measured after a fresh bulk load. */
    private const int BytesPerView = 450;

    /** @var array<string, float> seconds per step */
    private array $timings = [];

    public function handle(TrafficSimulator $traffic): int
    {
        $views = max(0, (int) str_replace(['_', ','], '', (string) $this->option('views')));
        $days = max(1, (int) $this->option('days'));
        $focus = $this->option('focus');
        $bulk = ! $this->option('no-bulk') && ($this->option('bulk') || $views >= 1_000_000);

        if ($focus !== null && ! in_array($focus, ['post', 'episode'], true)) {
            $this->components->error('--focus takes post or episode.');

            return self::INVALID;
        }

        $virals = $this->virals();

        if ($virals === null) {
            return self::INVALID;
        }

        $total = $views + array_sum($virals);

        $this->components->info(sprintf(
            'Rebuilding Inkwell with %s views over %d days%s.',
            number_format($total),
            $days,
            $focus ? ", most of them on {$focus}s" : '',
        ));
        $this->components->twoColumnDetail('Estimated size of the views table', $this->size($total * self::BytesPerView).', before rollups');

        if ($total > 5_000_000 && ! $this->option('force') && $this->input->isInteractive() && ! $this->confirm('That is a large load. Continue?', true)) {
            return self::FAILURE;
        }

        $this->step('Fresh database and content', function (): void {
            Autopilot::set(false);
            Artisan::call('cache:clear');
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => ContentSeeder::class, '--force' => true]);
        });

        $indexes = [];

        if ($bulk) {
            $this->step('Set the views indexes aside', function () use (&$indexes): void {
                $indexes = ViewsIndexes::suspend();
            });
        }

        try {
            $this->step('Simulate '.number_format($views).' views', function () use ($traffic, $views, $days, $focus): void {
                $bar = $this->bar($views);

                $traffic->focus($focus)->history($days, $views, function (int $day, int $of, int $written) use ($bar): void {
                    $bar->setProgress(min($bar->getMaxSteps(), $written));
                });

                $bar->finish();
                $this->newLine();
            });

            foreach ($virals as $slug => $count) {
                $post = Post::query()->where('slug', $slug)->firstOrFail();

                $this->step("Make “{$post->title}” go viral", function () use ($traffic, $post, $count): void {
                    $bar = $this->bar($count);

                    $traffic->viral($post, $count, progress: fn (int $written) => $bar->setProgress(min($count, $written)));

                    $bar->finish();
                    $this->newLine();
                });
            }
        } finally {
            if ($indexes !== []) {
                $this->step('Build the views indexes', fn () => ViewsIndexes::restore($indexes));
            }
        }

        if (! $this->option('skip-derived')) {
            // What the scheduler would have done: the pairs table that
            // alsoViewed() reads, and the counter columns. The first recount
            // marks every post, so no milestone fires for the past.
            $this->step('views:pairs', fn () => Artisan::call('views:pairs'));
            $this->step('views:recount --full', fn () => Artisan::call('views:recount', ['--full' => true]));
        }

        DemoEvent::query()->delete();
        DemoEvent::query()->create([
            'event' => 'Seeded',
            'tone' => 'muted',
            'title' => 'Seeded '.number_format(DB::table('views')->count())." views over {$days} days",
            'payload' => ['views' => $views, 'days' => $days, 'focus' => $focus, 'viral' => $virals, 'bulk' => $bulk],
        ]);

        $this->summary();

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>|null
     */
    private function virals(): ?array
    {
        $virals = [];

        foreach ($this->option('viral') as $option) {
            [$slug, $count] = array_pad(explode(':', $option, 2), 2, '1000000');
            $count = (int) str_replace(['_', ','], '', $count);

            // Checked against the catalog: the database is rebuilt after this.
            $published = collect(Catalog::posts())->reject(fn (array $post): bool => $post['age'] === null)->map(fn (array $post): string => Str::slug($post['title']));

            if ($count < 1 || ! $published->contains($slug)) {
                $this->components->error("--viral={$option}: name a published post by its slug, with a number of views.");

                return null;
            }

            $virals[$slug] = $count;
        }

        return $virals;
    }

    private function step(string $label, callable $work): void
    {
        $this->components->task($label, function () use ($label, $work): bool {
            $started = microtime(true);

            try {
                $work();
            } catch (Throwable $e) {
                $this->timings[$label] = microtime(true) - $started;

                throw $e;
            }

            $this->timings[$label] = microtime(true) - $started;

            return true;
        });
    }

    private function bar(int $max): ProgressBar
    {
        $bar = $this->output->createProgressBar(max(1, $max));
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% / %estimated:-6s% %memory:6s%');
        $bar->setRedrawFrequency(max(1, intdiv($max, 200)));

        return $bar;
    }

    private function summary(): void
    {
        $this->newLine();

        foreach ($this->timings as $label => $seconds) {
            $this->components->twoColumnDetail($label, $seconds >= 60 ? intdiv((int) $seconds, 60).'m '.((int) $seconds % 60).'s' : number_format($seconds, 1).'s');
        }

        $rows = DB::table('views')->count();
        $this->components->twoColumnDetail('<fg=green>Views in the table</>', number_format($rows));

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('analyze table views');
            $size = DB::selectOne('select data_length + index_length as bytes from information_schema.tables where table_schema = database() and table_name = ?', ['views']);
            $this->components->twoColumnDetail('Size on disk', $this->size((int) $size->bytes).($rows > 0 ? ', '.round($size->bytes / $rows).' bytes a view' : ''));
        }

        $this->newLine();
        $this->components->bulletList([
            'Run views:maintain on the Console to fold rollups, anonymise and prune. With millions of views, give it --max-seconds and let it run a few times.',
            'Start at https://eloquent-viewable-demo.localhost/tour',
        ]);
    }

    private function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 1).' GB',
            $bytes >= 1_048_576 => round($bytes / 1_048_576).' MB',
            default => round($bytes / 1_024).' KB',
        };
    }
}
