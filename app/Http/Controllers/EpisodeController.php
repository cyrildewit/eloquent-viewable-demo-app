<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Post;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class EpisodeController extends Controller
{
    public function index(): View
    {
        return view('episodes.index', [
            'episodes' => Episode::query()->published()->latest('published_at')->get(),
            'topPlayed' => views(Episode::class)->collection('plays')->period(Period::pastDays(30))->remember(10)->top(3),
        ]);
    }

    /**
     * No views() call here: the `views` middleware on the route records the
     * page view after a successful response, with a 10 minute cooldown.
     */
    public function show(Episode $episode): View
    {
        abort_unless($episode->published_at?->isPast(), 404);

        return view('episodes.show', [
            'episode' => $episode,
            'collections' => views($episode)->countByCollection(),
            'plays' => views($episode)->collection('plays')->period(Period::pastDays(30))->remember(5)->countByDay(),
            'alsoRead' => views($episode)->remember(10)->alsoViewed(4, among: Post::class),
        ]);
    }

    /**
     * A play is a view in the `plays` collection. It is queued, so the insert
     * happens in the evd-horizon container instead of during this request.
     */
    public function play(Episode $episode): JsonResponse
    {
        $result = views($episode)->collection('plays')->queue()->attempt();

        return response()->json($result);
    }
}
