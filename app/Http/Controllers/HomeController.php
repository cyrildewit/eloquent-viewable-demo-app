<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Post;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Recommendations are personal: for a reader from their account, for
        // a guest from the visitor cookie. An empty list is a valid answer,
        // so fall back to what is trending.
        // Thirty days of history is the better signal and reads far fewer
        // rows. Remembered per reader for ten minutes.
        $recommended = views(Post::class)
            ->viewedBy($user)
            ->period(Period::pastDays(30))
            ->remember(10)
            ->recommended(4);

        $episodes = Episode::query()->published()->latest('published_at')->limit(3)->get();

        return view('home', [
            // A Reddit-style hot score, kept in a counter column by views:recount.
            'hot' => Post::query()->published()->with('author')->orderByHot()->limit(7)->get(),
            'trending' => views(Post::class)->remember(5)->trending(5),
            'mostRead' => Views::period(Period::pastDays(7))->remember(10)->top(5),
            'rising' => views(Post::class)->period(Period::pastDays(1))->remember(10)->rising(3, minimum: 5),
            'live' => rescue(fn () => Views::live()->top(5), null, false),
            'recommended' => $recommended,
            'recommendedFallback' => $recommended->isEmpty() ? views(Post::class)->remember(5)->trending(4) : null,
            'episodes' => $episodes,
            'plays' => Views::forViewables($episodes)->collection('plays')->remember(10)->counts(),
        ]);
    }
}
