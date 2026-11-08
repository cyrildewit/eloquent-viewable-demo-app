<?php

namespace App\Http\Controllers;

use App\Demo\FrontPage;
use App\Models\Episode;
use App\Models\Post;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Nothing slow runs here: the panels come from the cache that FrontPage
     * keeps, and recommendations are worked out in the queue.
     */
    public function __invoke(Request $request, FrontPage $front, Visitor $visitor): View
    {
        $shared = $front->shared();

        return view('home', [
            ...$shared,
            // A Reddit-style hot score, kept in a counter column by views:recount.
            'hot' => Post::query()->published()->with('author')->orderByHot()->limit(7)->get(),
            'live' => rescue(fn () => Views::live()->top(5), null, false),
            'recommended' => $front->recommendations($request->user(), $visitor->id()),
            'episodes' => Episode::query()->published()->latest('published_at')->limit(3)->get(),
        ]);
    }
}
