<?php

namespace App\Providers;

use App\Demo\Catalog;
use App\Demo\QueryMeter;
use App\Listeners\LogViewableEvents;
use App\Models\Episode;
use App\Models\Post;
use App\Models\User;
use App\Viewable\Curves\PlateauDecay;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, so every request and queued job starts at zero.
        $this->app->scoped(QueryMeter::class);

        $this->app->bind(PlateauDecay::class, fn (): PlateauDecay => new PlateauDecay(
            CarbonInterval::hours(6),
            CarbonInterval::day(),
        ));
    }

    public function boot(): void
    {
        // Short morph aliases: the views table stores `post` instead of
        // `App\Models\Post`, and the beacon URL shows them too.
        Relation::enforceMorphMap([
            'post' => Post::class,
            'episode' => Episode::class,
            'user' => User::class,
        ]);

        Views::macro('countByDay', function () {
            /** @var Views $this */
            return $this->countByInterval(Granularity::Day);
        });

        Event::subscribe(LogViewableEvents::class);

        DB::listen(fn (QueryExecuted $query) => app(QueryMeter::class)->record($query->time));

        View::composer('components.layout', function ($view): void {
            $view->with([
                // Presence lives in Redis; a stopped Redis should not take the page down.
                'navLive' => rescue(fn (): int => ViewsFacade::live()->count(), null, false),
                'people' => User::query()->whereIn('email', array_column(Catalog::people(), 'email'))->orderBy('id')->get(),
                'me' => auth()->user(),
                'switches' => [
                    'identity' => config('eloquent-viewable.visitor.identity'),
                    'store' => config('eloquent-viewable.recording.store.driver'),
                    'queue' => config('eloquent-viewable.recording.queue.enabled') ? 'on' : 'off',
                    'source' => config('eloquent-viewable.querying.source.driver'),
                ],
            ]);
        });
    }
}
