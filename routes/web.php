<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostStatsController;
use App\Http\Controllers\StatsController;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Articles: recorded in the controller with attempt(), so the page can show
// what happened to the view.
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::post('/posts/{post}/heartbeat', [PostController::class, 'heartbeat'])->name('posts.heartbeat');
Route::get('/posts/{post}/stats/{period?}', PostStatsController::class)->name('posts.stats');

// The same article from a full-page cache: only the beacon can record it.
Route::get('/lite/{post}', [PostController::class, 'lite'])->name('posts.lite');

// Episodes: recorded by the `views` route middleware, no code in the controller.
Route::get('/episodes', [EpisodeController::class, 'index'])->name('episodes.index');
Route::get('/episodes/{episode}', [EpisodeController::class, 'show'])
    ->middleware(RecordViews::using('episode', cooldown: 10))
    ->name('episodes.show');
Route::post('/episodes/{episode}/play', [EpisodeController::class, 'play'])->name('episodes.play');

Route::get('/stats/live.json', [StatsController::class, 'live'])->name('stats.live');
Route::get('/stats/{period?}', [StatsController::class, 'index'])->name('stats');

Route::get('/lab', [LabController::class, 'index'])->name('lab');
Route::post('/lab', [LabController::class, 'attempt'])->name('lab.attempt');
Route::post('/lab/burst', [LabController::class, 'burst'])->name('lab.burst');
Route::post('/lab/visitor', [LabController::class, 'newVisitor'])->name('lab.visitor');

Route::get('/console', [ConsoleController::class, 'index'])->name('console');
Route::post('/console/run', [ConsoleController::class, 'run'])->name('console.run');
Route::get('/console/runs/{id}', [ConsoleController::class, 'status'])->name('console.runs.show');
Route::post('/console/examine', [ConsoleController::class, 'examine'])->name('console.examine');
Route::post('/console/simulate', [ConsoleController::class, 'simulate'])->name('console.simulate');
Route::post('/console/autopilot', [ConsoleController::class, 'autopilot'])->name('console.autopilot');
Route::post('/console/events/clear', [ConsoleController::class, 'clearEvents'])->name('console.events.clear');

Route::get('/account', [AccountController::class, 'index'])->name('account');
Route::post('/account/tracking', [AccountController::class, 'tracking'])->name('account.tracking');
Route::get('/account/export', [AccountController::class, 'export'])->name('account.export');
Route::post('/account/anonymise', [AccountController::class, 'anonymise'])->name('account.anonymise');
Route::post('/account/forget', [AccountController::class, 'forget'])->name('account.forget');

// One click sign-in for the demo readers. Never do this in a real app.
Route::post('/demo/login/{user}', [DemoLoginController::class, 'login'])->name('demo.login');
Route::post('/demo/logout', [DemoLoginController::class, 'logout'])->name('demo.logout');

Route::view('/tour', 'tour')->name('tour');
