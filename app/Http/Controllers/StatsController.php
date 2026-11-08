<?php

namespace App\Http\Controllers;

use App\Demo\Answer;
use App\Models\Episode;
use App\Models\Post;
use App\Viewable\Curves\PlateauDecay;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\LinearDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\Window;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public const array PERIODS = ['24h' => 'Last 24 hours', '7d' => '7 days', '30d' => '30 days', '3m' => '3 months', '1y' => '1 year', '2y' => '2 years'];

    /** Minutes a stats read is remembered. demo:warm refreshes them more often. */
    public const int CacheMinutes = 15;

    public const array TIMEZONES = ['UTC', 'Europe/Amsterdam', 'America/New_York', 'Asia/Tokyo', 'Australia/Sydney'];

    /**
     * The period comes straight from the URL: Period binds as a route
     * parameter, so /stats/7d, /stats/12h and /stats/2026-01-01..2026-02-01
     * all work.
     */
    public function index(Request $request, ?Period $period = null): View
    {
        $period ??= Period::parse('30d');
        $type = in_array($request->query('type'), ['post', 'episode'], true) ? $request->query('type') : 'post';
        $timezone = in_array($request->query('tz'), self::TIMEZONES, true) ? $request->query('tz') : 'UTC';

        return view('stats.index', [
            ...$this->report($period, $type, $timezone),
            'live' => rescue(fn () => ['count' => Views::live()->count(), 'top' => Views::live()->top(6)], null, false),
        ]);
    }

    /**
     * Everything the page shows but the live panel, each read remembered for
     * CacheMinutes. demo:warm calls this on a schedule for the periods in
     * PERIODS, so a visitor finds the answers in the cache.
     *
     * @return array<string, mixed>
     */
    public function report(Period $period, string $type, string $timezone = 'UTC'): array
    {
        $model = $type === 'post' ? Post::class : Episode::class;

        $views = fn () => views($model)->period($period)->timezone($timezone)->remember(self::CacheMinutes);

        $granularity = $this->granularity($period);
        $lastHour = Period::create(now()->subHour()->startOfHour(), now()->startOfHour());

        return [
            'period' => $period,
            'periodKey' => $period->getRouteKey(),
            'type' => $type,
            'timezone' => $timezone,
            'granularity' => $granularity,

            'views' => $views()->compare(),
            'visitors' => $views()->unique()->compare(),
            'returning' => $views()->returning()->compare(),
            'series' => $views()->countByInterval($granularity),
            'top' => $views()->top(8),
            'collections' => $views()->countByCollection(),
            'frequency' => $views()->countByFrequency(upTo: 4),

            'dimensions' => collect(['source', 'medium', 'device', 'country', 'campaign', 'plan'])
                ->mapWithKeys(fn (string $dimension) => [$dimension => Answer::of(fn () => $views()->countBy($dimension, limit: 6))]),
            'googleMobile' => Answer::of(fn () => $views()->whereDimension('source', 'Google')->whereDimension('device', 'mobile')->count()),

            'crossType' => Views::period($period)->remember(self::CacheMinutes)->top(5),
            'rising' => views($model)->period(Period::pastDays(1))->remember(10)->rising(5, minimum: 5),
            'spikes' => views($model)->period($lastHour)->remember(10)->anomalies(threshold: 2.5, minimum: 3, limit: 5),
            'drops' => views($model)->period($lastHour)->remember(10)->anomalies(threshold: -2.5, minimum: 3, limit: 5),
            'dailySeason' => views($model)->period(Period::create(now()->subDay()->startOfDay(), now()->startOfDay()))->remember(10)->anomalies(threshold: 2, minimum: 5, limit: 5, seasonality: Seasonality::Day, samples: 7),

            'curves' => [
                'Exponential, half-life 1 day' => ['views(Post::class)->trending(5)', views($model)->remember(10)->trending(5)],
                'Exponential, half-life 1 week' => ['->trending(5, halfLife: CarbonInterval::week())', views($model)->remember(10)->trending(5, halfLife: CarbonInterval::week())],
                'Linear, fades out over 7 days' => ['->trending(5, curve: new LinearDecay(...days(7)))', views($model)->remember(10)->trending(5, curve: new LinearDecay(CarbonInterval::days(7)))],
                'Window, every view of 7 days counts' => ['->trending(5, curve: new Window(...days(7)))', views($model)->remember(10)->trending(5, curve: new Window(CarbonInterval::days(7)))],
                'Plateau: 6 hours flat, then halves daily' => ['->trending(5, curve: app(PlateauDecay::class))', views($model)->remember(10)->trending(5, curve: app(PlateauDecay::class))],
            ],

        ];
    }

    /**
     * Polled by the live panel every few seconds.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'count' => Views::live()->count(),
            'minute' => Views::live()->within(60)->count(),
            'posts' => views(Post::class)->live()->count(),
            'episodes' => views(Episode::class)->live()->count(),
            'top' => Views::live()->top(6),
        ]);
    }

    private function granularity(Period $period): Granularity
    {
        $days = $period->getStartDateTime()?->diffInDays($period->endOrNow()) ?? 365;

        return match (true) {
            $days <= 2 => Granularity::Hour,
            $days <= 120 => Granularity::Day,
            $days <= 400 => Granularity::Week,
            default => Granularity::Month,
        };
    }
}
