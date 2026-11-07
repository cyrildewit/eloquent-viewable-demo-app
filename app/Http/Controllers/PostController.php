<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Post;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PostController extends Controller
{
    /**
     * Every sort is a scope of InteractsWithViews, or a counter column the
     * scheduler keeps up to date.
     */
    public const array SORTS = [
        'latest' => ['Latest', "Post::published()->latest('published_at')"],
        'hot' => ['Hot', 'Post::orderByHot()'],
        'trending' => ['Trending', 'Post::orderByTrending()->withTrendingScore()'],
        'week' => ['Most read this week', "Post::orderByViews('desc', Period::pastDays(7))"],
        'popular' => ['All-time', "Post::orderByDesc('total_views') // counter column"],
        'google' => ['Popular on Google', "Post::orderByViews(period: Period::pastDays(30), dimensions: ['source' => 'Google'])"],
        'club' => ['1,000+ club', "Post::whereViewsCount('>=', 1000)->orderByViews()"],
        'unread' => ['Not opened yet', 'Post::whereNotViewedBy($user)'],
    ];

    public function index(Request $request, Visitor $visitor): View
    {
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : 'latest';

        $query = Post::query()->published()->with('author');

        match ($sort) {
            'latest' => $query->latest('published_at'),
            'hot' => $query->orderByHot(),
            'trending' => $query->orderByTrending()->withTrendingScore(),
            'week' => $query->orderByViews('desc', Period::pastDays(7), as: 'week_views'),
            'popular' => $query->orderByDesc('total_views'),
            'google' => $query->orderByViews(period: Period::pastDays(30), as: 'google_views', dimensions: ['source' => 'Google']),
            'club' => $query->whereViewsCount('>=', 1000)->orderByViews(),
            'unread' => $request->user()
                ? $query->whereNotViewedBy($request->user())->latest('published_at')
                : $query->whereNotViewedByVisitor($visitor->id())->latest('published_at'),
        };

        $posts = $query->paginate(9)->withQueryString();

        return view('posts.index', [
            'posts' => $posts,
            'sort' => $sort,
            'sorts' => self::SORTS,
            // One query for the whole page instead of one per post.
            'weekly' => Views::forViewables($posts->getCollection())->period(Period::pastDays(7))->remember(5)->counts(),
        ]);
    }

    public function show(Request $request, Post $post): View
    {
        abort_unless($post->published_at?->isPast(), 404);

        // attempt() records like record(), but says what happened, so the
        // receipt on the page can show which guard refused the view.
        $result = views($post)->cooldown(5)->attempt();

        $user = $request->user();

        return view('posts.show', [
            'post' => $post->load('author'),
            'result' => $result,
            'total' => views($post)->count(),
            'unique' => views($post)->unique()->period(Period::pastDays(30))->remember(5)->count(),
            'week' => views($post)->period(Period::pastDays(7))->remember(5)->compare(),
            'series' => views($post)->period(Period::pastDays(30))->remember(5)->countByDay(),
            'sources' => views($post)->period(Period::pastDays(30))->remember(5)->countBy('source', limit: 4),
            'alsoRead' => views($post)->remember(10)->alsoViewed(4, among: Post::class),
            'alsoListened' => views($post)->remember(10)->alsoViewed(2, among: Episode::class),
            'active' => rescue(fn (): int => views($post)->activeVisitors(), null, false),
            'lastVisit' => $user?->lastViewedAt($post),
            'heartbeat' => config('eloquent-viewable.presence.heartbeat'),
        ]);
    }

    /**
     * The page's own heartbeat: keeps this reader active in presence without
     * recording a view, and answers with who else is reading.
     */
    public function heartbeat(Post $post): JsonResponse
    {
        views($post)->heartbeat();

        $viewers = views($post)->live()->viewers(4);

        return response()->json([
            'active' => views($post)->activeVisitors(),
            'viewers' => $viewers->map(fn ($user) => ['name' => $user->name, 'initials' => $user->initials(), 'hue' => $user->hue])->values(),
        ]);
    }

    /**
     * A "lite" edition served from a full-page cache. The controller does not
     * record anything: the page is the same for everyone and may be minutes
     * old. The beacon in it posts back once the page has loaded.
     */
    public function lite(Post $post): Response
    {
        $html = Cache::remember("lite:{$post->id}", now()->addMinutes(10), fn (): string => view('posts.lite', [
            'post' => $post->load('author'),
            'renderedAt' => now(),
        ])->render());

        return response($html)->header('Cache-Control', 'public, max-age=600');
    }
}
