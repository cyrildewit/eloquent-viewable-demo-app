<?php

namespace App\Http\Controllers;

use App\Demo\Answer;
use App\Models\Episode;
use App\Models\Post;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * One post's stats page, the kind an author would see.
 */
class PostStatsController extends Controller
{
    public function __invoke(Post $post, ?Period $period = null): View
    {
        $period ??= Period::parse('30d');

        $views = fn () => views($post)->period($period)->remember(30);
        $days = $period->getStartDateTime()?->diffInDays($period->endOrNow()) ?? 365;
        $granularity = $days <= 2 ? Granularity::Hour : ($days <= 120 ? Granularity::Day : Granularity::Week);

        $lastHour = Period::create(now()->subHour()->startOfHour(), now()->startOfHour());

        return view('stats.post', [
            'post' => $post,
            'period' => $period,
            'periodKey' => $period->getRouteKey(),
            'granularity' => $granularity,

            'views' => $views()->compare(),
            'visitors' => $views()->unique()->compare(),
            'returning' => $views()->returning()->compare(),
            'series' => $views()->countByInterval($granularity),
            'hours' => views($post)->period(Period::subHours(48))->countByInterval(Granularity::Hour),
            'frequency' => $views()->countByFrequency(upTo: 5),
            'collections' => $views()->countByCollection(),
            'sources' => Answer::of(fn () => $views()->countBy('source', limit: 8)),
            'devices' => Answer::of(fn () => $views()->countBy('device')),
            'countries' => Answer::of(fn () => $views()->countBy('country', limit: 8)),
            'referrers' => Answer::of(fn () => $views()->countBy('referrer_host', limit: 6)),
            'googleMobile' => Answer::of(fn () => $views()->whereDimension('source', ['Google', 'Bing', 'DuckDuckGo'])->countBy('device')),

            'baseline' => views($post)->period($lastHour)->againstBaseline(Seasonality::Week, samples: 4),
            'newsletter' => views($post)->rollup('newsletter')->period(Period::pastYears(2))->countByDimension(),

            'alsoRead' => views($post)->remember(10)->alsoViewed(5, among: Post::class),
            'alsoListened' => views($post)->remember(10)->alsoViewed(3, among: Episode::class),

            // Fresh counts next to the counter columns views:recount wrote,
            // so you can see how far behind the columns are.
            'live' => [
                'total_views' => views($post)->count(),
                'unique_viewers' => views($post)->unique()->count(),
                'views_last_week' => views($post)->period(Period::pastDays(7))->count(),
                'google_views_30d' => views($post)->period(Period::pastDays(30))->whereDimension('source', 'Google')->count(),
            ],
            'milestones' => DB::table(config('eloquent-viewable.milestones.table'))
                ->where('viewable_type', $post->getMorphClass())
                ->where('viewable_id', $post->getKey())
                ->get(),
            'spikes' => DB::table(config('eloquent-viewable.spikes.table'))
                ->where('viewable_type', $post->getMorphClass())
                ->where('viewable_id', $post->getKey())
                ->get(),
        ]);
    }
}
