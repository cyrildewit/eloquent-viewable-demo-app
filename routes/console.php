<?php

use App\Demo\Autopilot;
use App\Demo\TrafficSimulator;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| The schedule of a production app using Eloquent Viewable
|--------------------------------------------------------------------------
|
| The evd-scheduler container runs these. Maintenance only runs while the
| autopilot is on (a switch on the Console page), so the seeded data stays
| put until you choose to change it. views:doctor still sees every command
| as scheduled.
|
*/

// Roll up, anonymise, prune and recount counter columns.
Schedule::command('views:maintain --max-seconds=600')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping()
    ->when(fn (): bool => Autopilot::enabled());

// Compare the last closed hour with the same hour on past weeks.
Schedule::command('views:detect-spikes')
    ->hourly()
    ->onOneServer()
    ->when(fn (): bool => Autopilot::enabled());

// Rewrite the pairs table alsoViewed() and recommended() read.
Schedule::command('views:pairs')
    ->hourlyAt(30)
    ->onOneServer()
    ->when(fn (): bool => Autopilot::enabled());

// Land views buffered in Redis. Not tied to the autopilot: a buffered view
// should never wait. The database store has nothing to flush and says so
// with an error, so it only runs with VIEWABLE_STORE=redis.
Schedule::command('views:flush')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn (): bool => config('eloquent-viewable.recording.store.driver') === 'redis');

// Keep the Stats page's cache filled, so no visitor waits for a cold read.
// Read-only, so it runs without the autopilot. Each read is remembered for
// 15 minutes; a run only recomputes what expired or was forgotten.
Schedule::command('demo:warm')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Horizon's throughput and runtime graphs are built from these snapshots.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// A trickle of readers, so the live counters and trending move on their own.
Schedule::call(fn () => app(TrafficSimulator::class)->recent(views: mt_rand(4, 12), minutes: 1))
    ->name('demo:trickle')
    ->everyMinute()
    ->when(fn (): bool => Autopilot::enabled());
