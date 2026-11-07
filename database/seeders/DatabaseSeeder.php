<?php

namespace Database\Seeders;

use App\Demo\TrafficSimulator;
use App\Models\DemoEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * The content and a modest history, for `migrate --seed` and the tests.
 * `php artisan demo:reset` does the same with options for millions of views.
 */
class DatabaseSeeder extends Seeder
{
    public function run(TrafficSimulator $traffic): void
    {
        $this->call(ContentSeeder::class);

        $days = (int) env('DEMO_DAYS', 730);
        $views = $traffic->history($days, (int) env('DEMO_VIEWS', 110_000));

        Artisan::call('views:pairs');
        Artisan::call('views:recount', ['--full' => true]);

        DemoEvent::query()->delete();
        DemoEvent::query()->create([
            'event' => 'Seeded',
            'tone' => 'muted',
            'title' => 'Seeded '.number_format($views)." views over {$days} days",
        ]);
    }
}
