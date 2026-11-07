<?php

namespace App\Console\Commands;

use App\Demo\Examiner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Examines the package's tables for the Console. Counting tens of millions
 * of rows takes seconds, so it runs in the background after every command,
 * and the page shows the last snapshot.
 */
class ExamineDemo extends Command
{
    public const string CacheKey = 'demo:examine';

    protected $signature = 'demo:examine {--counts : Only print the table counts as JSON}';

    protected $description = 'Snapshot the views, rollups and doctor findings for the Console';

    public function handle(Examiner $examiner): int
    {
        if ($this->option('counts')) {
            $this->line(json_encode($examiner->counts()));

            return self::SUCCESS;
        }

        $started = microtime(true);

        $doctor = new BufferedOutput;
        Artisan::call('views:doctor', ['--json' => true], $doctor);

        // Plain arrays only: Laravel 13 refuses to unserialize objects from the
        // cache (`cache.serializable_classes`), so a collection or a database
        // row would come back as an incomplete class.
        Cache::forever(self::CacheKey, json_decode(json_encode([
            'counts' => $examiner->counts(),
            'views' => $examiner->views(),
            'timeline' => $examiner->timeline(),
            'rollups' => $examiner->rollups(),
            'doctor' => json_decode($doctor->fetch(), true),
            'at' => now()->toIso8601String(),
            'seconds' => round(microtime(true) - $started, 1),
        ]), true));

        $this->components->info('Snapshot taken in '.round(microtime(true) - $started, 1).'s.');

        return self::SUCCESS;
    }
}
