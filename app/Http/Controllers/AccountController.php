<?php

namespace App\Http\Controllers;

use App\Demo\Terminal;
use App\Models\Post;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\View\View as ViewResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A reader's own page: what they read, what is recommended and why, and the
 * privacy controls a GDPR request needs.
 */
class AccountController extends Controller
{
    public function index(Request $request, Visitor $visitor): ViewResponse
    {
        $user = $request->user();
        $visitorId = $visitor->id();

        if ($user === null) {
            return view('account.guest', [
                'visitorId' => $visitorId,
                'history' => View::query()->byVisitor($visitorId)->with('viewable')->latest('viewed_at')->limit(12)->get(),
                'total' => View::query()->byVisitor($visitorId)->count(),
                'recommended' => views(Post::class)->recommended(4),
                'unread' => Post::query()->published()->whereNotViewedByVisitor($visitorId)->latest('published_at')->limit(4)->get(),
            ]);
        }

        return view('account.user', [
            'user' => $user,
            'visitorId' => $visitorId,
            'history' => $user->viewed()->with('viewable')->paginate(12),
            'monthly' => views(Post::class)->viewedBy($user)->period(Period::pastDays(30))->count(),
            'recommended' => $user->recommended(Post::class, limit: 6, period: Period::pastDays(90)),
            'scoped' => Post::query()->published()->recommendedFor($user, period: Period::pastDays(90))->limit(3)->get(),
            'unread' => Post::query()->published()->whereNotViewedBy($user)->latest('published_at')->limit(4)->get(),
            'readThisWeek' => Post::query()->published()->whereViewedBy($user, Period::pastDays(7))->count(),
        ]);
    }

    public function tracking(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['tracks_reading' => $request->boolean('enabled')]);

        return back()->with('status', $user->tracks_reading
            ? 'Reading history is on again. New views are recorded.'
            : 'Reading history is off. The IgnoreOptedOutViewers guard now drops your views; the ones already recorded are kept until you forget or anonymise them.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->streamDownload(function () use ($user): void {
            echo $user->exportViewHistory()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, 'inkwell-reading-history.json', ['Content-Type' => 'application/json']);
    }

    public function anonymise(Request $request): RedirectResponse
    {
        $count = $request->user()->anonymiseViewHistory();

        return back()->with('status', "Anonymised {$count} views: the counts stay, but nothing ties them to you any more.");
    }

    public function forget(Request $request, Visitor $visitor): RedirectResponse
    {
        if ($user = $request->user()) {
            $count = $user->forgetViewHistory(includeGuestViews: $request->boolean('guests'));

            return back()->with('status', "Deleted {$count} views. Counter columns of the posts you read were recounted right away.");
        }

        Terminal::artisan('views:forget-visitor', [$visitor->id(), '--force']);

        return back()->with('status', 'Deleted every view of this browser with <code>views:forget-visitor</code>.');
    }
}
