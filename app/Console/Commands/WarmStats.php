<?php

namespace App\Console\Commands;

use App\Demo\FrontPage;
use App\Demo\Runs;
use App\Http\Controllers\StatsController;
use App\Jobs\ComputeRecommendations;
use App\Models\User;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fills the cache the Stats page reads, for every period it links to, and the
 * front page's shared panels and recommendations, so no visitor waits for a
 * cold read of millions of views. The README's advice
 * for heavy reports: compute them outside the request.
 *
 * It stands aside when warming would hurt more than it helps: while
 * maintenance works on the tables, and on a large table nothing is folded
 * in yet, where every read scans the views table and each fold step forgets
 * the answers again.
 */
class WarmStats extends Command
{
    protected $signature = 'demo:warm {--force : Warm even while maintenance runs or nothing is folded}';

    protected $description = 'Compute the Stats page and the front page ahead of their visitors';

    public function handle(StatsController $stats, FrontPage $front): int
    {
        if (! $this->option('force') && ($reason = $this->standAside()) !== null) {
            $this->components->info("Not warming: {$reason}");

            return self::SUCCESS;
        }

        $started = microtime(true);
        $front->refreshShared();
        $this->components->twoColumnDetail('front page panels', round((microtime(true) - $started) * 1_000).' ms');

        // Recommendations are per reader: queue them for the readers seen
        // in the last day, so their next front page has a fresh list.
        $readers = View::query()
            ->where('viewer_type', (new User)->getMorphClass())
            ->where('viewed_at', '>=', now()->subDay())
            ->distinct()
            ->pluck('viewer_id');

        foreach ($readers as $reader) {
            ComputeRecommendations::dispatch((int) $reader, null);
        }

        $this->components->twoColumnDetail('recommendations queued for active readers', (string) $readers->count());

        foreach (['post', 'episode'] as $type) {
            foreach (array_keys(StatsController::PERIODS) as $key) {
                $started = microtime(true);

                try {
                    // A read already in the cache returns at once, so a warm
                    // run only recomputes what expired or was forgotten.
                    $stats->report(Period::parse($key), $type);
                    $this->components->twoColumnDetail("{$type} {$key}", round((microtime(true) - $started) * 1_000).' ms');
                } catch (Throwable $e) {
                    report($e);
                    $this->components->twoColumnDetail("{$type} {$key}", '<fg=red>'.class_basename($e).'</>');
                }
            }
        }

        return self::SUCCESS;
    }

    private function standAside(): ?string
    {
        if (Runs::active() !== null) {
            return 'a command started from the Console is running.';
        }

        if ($this->maintenanceRunning()) {
            return 'views:maintain or another maintenance command holds its lock.';
        }

        $folded = DB::table('view_state')->where('name', 'rollup:views:day')->exists();

        if (! $folded && $this->estimatedViews() > 1_000_000) {
            return 'nothing is folded into the rollups yet, so every read would scan the views table. Run views:maintain first.';
        }

        return null;
    }

    /**
     * The package's maintenance commands share one lock in the cache store.
     * Reading the key, rather than taking the lock, never makes a starting
     * run find it taken.
     */
    private function maintenanceRunning(): bool
    {
        return (bool) rescue(function (): bool {
            $store = Cache::store(config('eloquent-viewable.querying.cache.store'))->getStore();

            if (! method_exists($store, 'lockConnection')) {
                return false;
            }

            return (bool) $store->lockConnection()->exists($store->getPrefix().config('eloquent-viewable.querying.cache.key').':maintenance');
        }, false, false);
    }

    private function estimatedViews(): int
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return DB::table('views')->count();
        }

        return (int) (DB::selectOne('select table_rows from information_schema.tables where table_schema = database() and table_name = ?', ['views'])->table_rows ?? 0);
    }
}
