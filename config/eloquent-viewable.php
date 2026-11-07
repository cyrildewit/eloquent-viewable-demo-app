<?php

use App\Models\Episode;
use App\Models\Post;
use App\Viewable\Dimensions\AcceptLanguageCountry;
use App\Viewable\Dimensions\PlanDimension;
use App\Viewable\Doctor\QueueBacklogCheck;
use App\Viewable\Guards\IgnoreAuthors;
use App\Viewable\Rollups\NewsletterViews;
use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\Country;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Medium;
use CyrildeWit\EloquentViewable\Dimensions\ReferrerHost;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Doctor\Checks\ConfigurationCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\CrawlerShareCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\DimensionsCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\IndexAdviceCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\RedisStreamCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\ScheduleCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\SchemaCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\SharedCacheCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\TrustedProxiesCheck;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreMissingUserAgent;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreOptedOutViewers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;

return [

    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    */
    'models' => [

        'view' => [

            'class' => View::class,

            'table_name' => 'views',

            /*
             * The database connection used to store views. When `null`, the
             * application's default database connection is used.
             */
            'connection' => null,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Recording
    |--------------------------------------------------------------------------
    |
    | Everything that happens when `views($post)->record()` is called.
    |
    */
    'recording' => [

        /*
         * Where a recorded view goes:
         *
         * - `database` writes it to the views table, during the request or
         *   from a queue worker when queueing is enabled.
         * - `redis` buffers it in a Redis stream until `views:flush` moves it
         *   to the views table in batches.
         * - `array` keeps it in memory for the process.
         * - `null` discards it.
         */
        'store' => [

            'driver' => env('VIEWABLE_STORE', 'database'),

            'redis' => [

                /*
                 * The Redis connection from `database.redis` that holds the
                 * stream. When `null`, the default connection is used.
                 */
                'connection' => null,

                'stream' => 'eloquent-viewable:views',

                'group' => 'eloquent-viewable',

                /*
                 * The store driver flushed views land in. Any driver but
                 * `redis` itself.
                 */
                'landing' => 'database',

            ],

        ],

        /*
         * The guards every recorded view passes. The first one that refuses
         * drops the view. Add or remove a guard to turn its check on or off,
         * or list your own class implementing `Recording\Contracts\RecordingGuard`.
         *
         *   IgnoreCrawlers              drops views whose user agent the
         *                               bound `CrawlerDetector` flags
         *   IgnoreMissingUserAgent      drops requests without a user agent,
         *                               such as scripts and health checks
         *   IgnoreIpAddresses           drops views from `ignored_ip_addresses`
         *   IgnoreHeadRequests          drops `HEAD` requests, such as uptime
         *                               monitors and link checkers
         *   IgnorePrefetch              drops prefetched and prerendered pages
         *   IgnoreOptedOutViewers       drops the views of a viewer whose
         *                               model implements `ViewerCanOptOut`
         *                               and returns false from
         *                               `tracksViews()`
         *   IgnoreBursts                drops a visitor that opens many
         *                               different models within seconds, see
         *                               `bursts`
         *   EnforceCooldown             drops a repeat view while a
         *                               `cooldown()` runs
         *   ThrottleVisitors            caps the views of one visitor per
         *                               minute, see `throttle`
         *   IgnoreDoNotTrack            honours the `DNT: 1` header
         *   IgnoreGlobalPrivacyControl  honours the `Sec-GPC: 1` header
         */
        'guards' => array_values(array_filter([
            IgnoreCrawlers::class,
            IgnoreMissingUserAgent::class,
            IgnoreIpAddresses::class,
            IgnoreHeadRequests::class,
            IgnorePrefetch::class,
            IgnoreOptedOutViewers::class,
            // A guard of the demo: an author reading their own post is not a reader.
            IgnoreAuthors::class,
            IgnoreBursts::class,
            EnforceCooldown::class,
            ThrottleVisitors::class,
            // Brave and some Firefox setups send these headers, which would hide
            // your own clicks while you explore, so they are opt-in.
            env('VIEWABLE_PRIVACY_SIGNALS', false) ? IgnoreDoNotTrack::class : null,
            env('VIEWABLE_PRIVACY_SIGNALS', false) ? IgnoreGlobalPrivacyControl::class : null,
        ])),

        /*
         * Views from these IP addresses are dropped by `IgnoreIpAddresses`.
         * A CIDR range such as `10.0.0.0/8` or `2001:db8::/32` drops every
         * address in it.
         */
        'ignored_ip_addresses' => [

            // The documentation range the Recording lab uses for its "office" visitor.
            '198.51.100.0/24',

        ],

        'throttle' => [

            /*
             * The most views one visitor may record per minute, counted
             * across all viewables. Views over the limit are dropped.
             */
            'max_per_minute' => 60,

            /*
             * The cache store that holds the counts. When `null`, the
             * default cache store is used.
             */
            'store' => null,

            'key' => 'cyrildewit.eloquent-viewable.throttle',

        ],

        'bursts' => [

            /*
             * When a visitor opens more than `max` different viewables within
             * `seconds`, `IgnoreBursts` drops that view and blocks all their
             * views for the next `block_for` seconds.
             */
            'max' => 8,

            'seconds' => 2,

            'block_for' => 120,

            /*
             * What a burst is counted per: `visitor`, the stored visitor id,
             * and `network`, a hash of the network and the user agent.
             */
            'by' => ['visitor', 'network'],

            /*
             * The cache store that holds the counts. When `null`, the
             * default cache store is used.
             */
            'store' => null,

            'key' => 'cyrildewit.eloquent-viewable.bursts',

        ],

        /*
         * When enabled, the signed-in model is stored as the viewer of each
         * view, in the `viewer_type` and `viewer_id` columns. It is off by
         * default because it links every view to a person.
         *
         * `guard` is the auth guard the signed-in model is read from. When
         * `null`, the application's default guard is used.
         *
         * `views($post)->viewedBy($user)` sets the viewer explicitly and
         * works without this setting.
         */
        'viewer' => [

            'enabled' => true,

            'guard' => null,

        ],

        /*
         * When enabled, views are stored by a queue worker instead of during
         * the request, so the database write does not slow down the response.
         *
         * `views($post)->queue()->record()` queues a single view, even when
         * this is disabled.
         */
        'queue' => [

            'enabled' => (bool) env('VIEWABLE_QUEUE', false),

            /*
             * The queue connection used to store views. When `null`, the
             * application's default queue connection is used.
             */
            'connection' => null,

            /*
             * The queue used to store views. When `null`, the default queue
             * of the connection is used.
             */
            'queue' => null,

        ],

        /*
         * When enabled, a route is registered that the browser posts to after
         * the page has loaded. Use it to record views of pages that are served
         * from a full-page cache or a CDN, and so never reach your controller.
         *
         * The `@viewsBeacon($post)` Blade directive prints the script that
         * calls this route.
         */
        'beacon' => [

            'enabled' => true,

            /*
             * The path prefix of the beacon route. Keep it neutral: privacy
             * filter lists block paths with words such as `beacon` or `track`.
             */
            'prefix' => '_ev',

            /*
             * Keep `web`, or a group of your own that starts the session and
             * checks the CSRF token.
             */
            'middleware' => ['web'],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Dimensions
    |--------------------------------------------------------------------------
    |
    | Count views by where they came from and who saw them. Each dimension
    | turns the request into one short value when a view is recorded, kept in
    | a column of its own, and `views($post)->countBy('source')` counts the
    | views per value. None is on until you list it.
    |
    */
    'dimensions' => [

        /*
         * The dimensions to record. Uncomment a line to turn one on.
         *
         * A dimension is kept in a column named after its key. After you add
         * one, run `php artisan views:dimensions` and migrate.
         *
         * An entry is a class, or an array of the class and its options, such
         * as `[Country::class, 'resolver' => HeaderCountry::class]`.
         */
        'definitions' => [

            'source' => Source::class,

            'medium' => Medium::class,

            // Campaign names in this demo carry no ids, so it may be folded into rollups.
            'campaign' => [Campaign::class, 'personal' => false],

            'device' => Device::class,

            // No Cloudflare in front of a laptop, so the demo reads CF-IPCountry and
            // falls back to the region of Accept-Language.
            'country' => [Country::class, 'resolver' => AcceptLanguageCountry::class],

            'referrer_host' => ReferrerHost::class,

            // A dimension of the demo, kept in `context` so it needs no column.
            'plan' => [PlanDimension::class, 'json' => 'context->plan', 'maxValues' => 5],

        ],

        /*
         * The hosts of the application itself, on top of the one in
         * `app.url`. A visitor arriving from one of them came from another
         * page of the site, so it is not counted as a referrer.
         */
        'internal_hosts' => [],

        /*
         * Hosts the `Source` and `Medium` dimensions should name, on top of
         * the list the package ships, each a source name and a medium.
         */
        'sources' => [

            'laravel-news.com' => ['Laravel News', 'referral'],

        ],

        /*
         * `utm_source` and `ref` values to read as a source name, such as
         * `'nl' => 'Newsletter'`.
         */
        'source_aliases' => [

            'nl' => 'Newsletter',

            'newsletter' => 'Newsletter',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Querying
    |--------------------------------------------------------------------------
    |
    | Everything that happens when a count is read: where the numbers come
    | from, how `remember()` caches them, and how large an interval series
    | may grow.
    |
    */
    'querying' => [

        /*
         * Where counts come from:
         *
         * - `database` reads the views table.
         * - `rollup` reads recent views from the views table and older
         *   history from the rollups in `retention.rollups`.
         *
         * Every count, such as `count()`, `countByInterval()` and `top()`,
         * reads from this source. The scopes do too, when the source can be
         * queried in SQL.
         */
        'source' => [

            'driver' => env('VIEWABLE_SOURCE', 'rollup'),

        ],

        'cache' => [

            'key' => 'cyrildewit.eloquent-viewable.cache',

            /*
             * The cache store cached counts are kept in. When `null`, the
             * default cache store is used.
             */
            'store' => null,

        ],

        /*
         * Counter columns on your own tables. `views:recount` and
         * `views:maintain` write the count into them, so a listing can order
         * and filter by a plain column.
         *
         * List a column by name to hold the all-time count, or map it to
         * options that narrow the count:
         *
         *   unique      count unique visitors only
         *   period      count only the views within this period, such as `7d`
         *   collection  count only the views in this collection
         *   dimensions  count only the views with these dimension values
         *   hot         hold the score `orderByHot()` sorts by, not a count
         *
         * For example:
         *
         *   Post::class => [
         *       'views_count',
         *       'unique_views_count' => ['unique' => true],
         *       'views_last_week' => ['period' => '7d'],
         *       'hot_score' => ['hot' => ['from' => 'published_at', 'every' => '12h']],
         *       'google_views' => ['dimensions' => ['source' => 'Google']],
         *   ],
         */
        'counters' => [

            Post::class => [
                'total_views',
                'unique_viewers' => ['unique' => true],
                'views_last_week' => ['period' => '7d'],
                'google_views_30d' => ['period' => '30d', 'dimensions' => ['source' => 'Google']],
                'hot_score' => ['hot' => ['from' => 'published_at', 'every' => '12h']],
            ],

            Episode::class => [
                'total_views',
                'plays' => ['collection' => 'plays'],
            ],

        ],

        /*
         * The most buckets one `countByInterval()` call may return. A call
         * that would return more throws instead of running.
         */
        'max_intervals' => 10_000,

        /*
         * `alsoViewed()` ranks what the visitors of a model also viewed.
         *
         *   minimum_visitors  leaves out a model fewer of them viewed
         *   max_visitors      how many of the most recent visitors are read,
         *                     or `null` to read them all
         */
        'also_viewed' => [

            'minimum_visitors' => 3,

            'max_visitors' => 1_000,

        ],

        /*
         * `recommended()` and `recommendedFor()` start from the models a
         * viewer recently viewed, and rank what other visitors of those
         * models also viewed. They use `also_viewed.minimum_visitors` too.
         *
         *   max_seeds     how many recently viewed models to start from
         *   max_visitors  how many recent visitors of each are read
         *   half_life     how long until a viewed model counts half as much
         *   similarity    `cosine` or `count`
         */
        'recommendations' => [

            'max_seeds' => 20,

            'max_visitors' => 500,

            'half_life' => '7d',

            'similarity' => 'cosine',

        ],

        /*
         * When enabled, `alsoViewed()` and `recommended()` read a few rows
         * from the pairs table instead of every view of every visitor. The
         * table keeps, for every model, the models its visitors also viewed,
         * ships with the package migrations and is filled by `views:pairs`,
         * which you schedule.
         *
         *   period     only the views within this period are paired
         *   max_pairs  how many models are kept per model, the ones it
         *              shares the most visitors with
         *
         * A call with a period, a collection or a filter skips the table and
         * reads the views table. Views recorded since the last run of
         * `views:pairs` are not in the table yet.
         */
        'pairs' => [

            'enabled' => (bool) env('VIEWABLE_PAIRS', true),

            'table' => 'view_pairs',

            'period' => '90d',

            'max_pairs' => 100,

        ],

        /*
         * `trending()` and `orderByTrending()` weigh each view by its age,
         * so recent views count more.
         *
         *   curve      `null` for the built-in curve, or a class implementing
         *              `Querying\Ranking\DecayCurve`
         *   half_life  how long the built-in curve takes to halve a weight
         *   step       `1h`, `1d`, or `auto` to pick one within `max_steps`
         *   max_steps  the most steps one call may weigh before it throws
         */
        'trending' => [

            'curve' => null,

            'half_life' => '1d',

            'step' => 'auto',

            'max_steps' => 500,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long views are kept as they were recorded. Nothing is set out of the
    | box, so nothing is anonymised or deleted until you say so. Durations use
    | the period shorthand: `30d`, `12w`, `6m`, `2y`. The `views:anonymise`,
    | `views:prune` and `views:maintain` commands apply them, and keep how far
    | they got in a table that ships with the package migrations.
    |
    */
    'retention' => [

        'anonymise' => [

            /*
             * Views older than this lose what ties them to a person. Must not
             * be longer than `prune.after`.
             */
            'after' => '30d',

            /*
             * The columns anonymising clears or re-hashes: any of `visitor`,
             * `viewer`, `context` and `dimensions`. `dimensions` stands for
             * the dimensions marked personal.
             */
            'columns' => ['visitor', 'viewer', 'context', 'dimensions'],

        ],

        'prune' => [

            /*
             * Views older than this are deleted.
             */
            'after' => '180d',

        ],

        /*
         * How many views one statement reads or writes in the commands that
         * process views in batches. Their `--chunk` option overrides it.
         */
        'chunk' => 5_000,

        /*
         * Rollups keep the counts of old views per bucket of time, so history
         * outlives the views it was counted from. `views:rollup` folds them
         * from the views table, and setting `querying.source.driver` to
         * `rollup` reads them. Their table ships with the package migrations.
         */
        'rollups' => [

            'table' => 'view_rollups',

            /*
             * The clock buckets align to. When `null`, the application's
             * timezone is used.
             */
            'timezone' => null,

            /*
             * How long a closed bucket waits for views that land late, from
             * a queue or the Redis buffer, before it is folded.
             */
            'settle' => '1h',

            /*
             * The tiers to keep, `hour`, `day`, `month` or `year`, each with
             * how long it is kept, or `null` for forever. A coarser tier must
             * be kept at least as long as a finer one. For example:
             * `['day' => '2y', 'month' => null]`.
             */
            'tiers' => [
                // The hour tier serves hourly trending and spike baselines once
                // views are pruned. It is opt-in in this demo: the first fold
                // walks every hour back to the oldest view, two years of them,
                // only to drop all but the last 35 days, which turns the first
                // views:maintain from seconds into minutes.
                ...(env('VIEWABLE_HOUR_TIER', false) ? ['hour' => '35d'] : []),
                'day' => '2y',
                'month' => null,
            ],

            /*
             * What a bucket is counted per: `viewable`, `viewable_collection`,
             * `type` or `type_collection`.
             */
            'groupings' => ['viewable', 'viewable_collection', 'type'],

            /*
             * Rollups of your own, each a class that extends
             * `Querying\Rollups\Rollup`: a filter, its tiers and groupings,
             * and at most one dimension. Read them with
             * `views($post)->rollup('name')`.
             */
            'custom' => [

                NewsletterViews::class,

            ],

            /*
             * Dimensions from `dimensions.definitions` to fold per value,
             * with the tiers and groupings above, so `countBy()` reads their
             * history from the rollups. Each bucket keeps the dimension's top
             * values and folds the rest into `other`. A dimension left out is
             * only counted while its views are in the views table.
             */
            'dimensions' => [

                'source',

                'device',

                'country',

            ],

            /*
             * When `true`, a count the rollups cannot answer exactly throws.
             */
            'strict' => false,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Milestones
    |--------------------------------------------------------------------------
    |
    | Thresholds a counter column can cross, such as a post's 10,000th view.
    | Each recount compares the columns it writes with them and dispatches
    | `ViewMilestoneReached` once per model and threshold. Their table ships
    | with the package migrations.
    |
    */
    'milestones' => [

        /*
         * The table that keeps the highest count each model crossed a
         * threshold at, so a threshold never fires twice.
         */
        'table' => 'view_milestones',

        /*
         * The thresholds of each counter column in `querying.counters`, in
         * ascending order. A column with a `period` goes up and down, so it
         * cannot have milestones. For example:
         *
         *   Post::class => [
         *       'views_count' => [100, 1_000, 10_000],
         *   ],
         */
        'thresholds' => [

            Post::class => [
                'total_views' => [100, 250, 500, 1_000, 2_500, 5_000, 10_000],
                'unique_viewers' => [100, 500, 1_000],
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Spikes
    |--------------------------------------------------------------------------
    |
    | `views:detect-spikes` compares each model's views in the last closed
    | window with the same window on past days or weeks, and dispatches
    | `ViewsSpiked` once when a model takes off, `ViewsDropped` when it
    | collapses, and `ViewsSettled` once it is back to normal. Schedule it as
    | often as the window is wide. Its table ships with the package
    | migrations.
    |
    */
    'spikes' => [

        /*
         * The table that keeps a row per model while it spikes or drops.
         */
        'table' => 'view_spikes',

        /*
         * The models to watch, each with its own settings. Every setting may
         * be left out for its default. For example:
         *
         *   Post::class => [
         *       'window' => '1h',        // whole hours, or `1d`
         *       'seasonality' => 'week', // `day` or `week`
         *       'samples' => 4,          // how many past windows to compare with
         *       'threshold' => 3.0,      // the z-score a spike starts at
         *       'minimum' => 50,         // views below which nothing counts
         *       'drops' => false,        // also watch for drops
         *       'cooldown' => '6h',      // how long it stays normal before it settles
         *   ],
         */
        'types' => [

            Post::class => [
                'window' => '1h',
                'seasonality' => 'week',
                'samples' => 4,
                'threshold' => 3.0,
                'minimum' => 5,
                'drops' => true,
                'cooldown' => '2h',
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Visitor
    |--------------------------------------------------------------------------
    |
    | How the package tells one visitor from the next. Visitors are bound to
    | their views through a cookie holding a random identifier.
    |
    */
    'visitor' => [

        /*
         * What identifies a visitor in the `visitor` column, which `unique()`
         * counts and a cooldown is keyed on. One of:
         *
         *   cookie   the random id from the cookie below, so a visitor is a
         *            browser
         *   viewer   an HMAC of the signed-in model's type and key with
         *            `app.key`, so a visitor is an account on every device and
         *            on an API without a cookie; guests get the cookie id
         *   fingerprint
         *            a hash of the IP address and user agent, so no cookie
         *            is set; signed-in models get the `viewer` id
         *
         * The signed-in model comes from `recording.viewer` or `viewedBy()`.
         */
        'identity' => env('VIEWABLE_IDENTITY', 'cookie'),

        'cookie' => [

            /*
             * The name of the cookie.
             */
            'name' => 'eloquent_viewable',

            /*
             * How long the cookie lives, in minutes. Five years by default.
             */
            'lifetime' => 2_628_000,

        ],

        'fingerprint' => [

            /*
             * How long one salt is used: `day`, `week` or `month`.
             */
            'rotation' => 'day',

            /*
             * The cache store the salt is kept in. When `null`, the default
             * cache store is used.
             */
            'store' => null,

            'key' => 'cyrildewit.eloquent-viewable.fingerprint',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    |
    | Who is looking at something right now: `views($post)->activeVisitors()`,
    | `Views::live()->top()` and the live counter of the beacon. A visitor is
    | active from the moment a view of theirs passes the guards, and stays
    | active while the page sends a heartbeat. A cooldown or the throttle does
    | not stop either. Kept apart from the views, so it works with any store.
    |
    */
    'presence' => [

        /*
         * Off by default. Reading a live count while it is off throws, so a
         * forgotten setting is not mistaken for an empty page.
         */
        'enabled' => true,

        /*
         * Where presence is kept: `redis`, `array`, which only lives as long
         * as the process and suits tests, or `null`. Register your own driver
         * with `PresenceManager::extend()`.
         */
        'driver' => 'redis',

        /*
         * How long a visitor stays active after they were last seen, in
         * seconds. `within()` can narrow it for one read, never widen it.
         */
        'window' => 120,

        /*
         * How the `redis` driver counts visitors: `exact`, or `approximate`
         * to keep memory flat on very busy sites.
         */
        'precision' => 'exact',

        /*
         * How often the beacon's live script tells the server the page is
         * still open, in seconds. Keep it well below the window.
         */
        'heartbeat' => 15,

        /*
         * Whether the heartbeat answers with the number of active visitors,
         * which the live script writes into every `[data-views-live]`
         * element on the page.
         */
        'expose_count' => true,

        /*
         * Whether the signed-in viewers are kept too, by their type and key,
         * for `live()->viewers()`. Off by default, because it keeps who is
         * looking rather than an anonymous id.
         */
        'viewers' => true,

        /*
         * The most recently seen viewables `live()->top()` ranks. One ranking
         * reads one count per candidate, so a higher value ranks more
         * viewables at the cost of more reads per call.
         */
        'max_candidates' => 1_000,

        'redis' => [

            /*
             * The Redis connection from `database.redis`. When `null`, the
             * default connection is used.
             */
            'connection' => null,

            'prefix' => 'eloquent-viewable:live',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cooldown
    |--------------------------------------------------------------------------
    */
    'cooldown' => [

        /*
         * Where running cooldowns are kept: `session`, which needs a session
         * on the route, or `cache`, which does not. Register your own
         * driver with `CooldownManager::extend()`.
         */
        'store' => 'cache',

        /*
         * The session key, or the cache key prefix, cooldowns are kept under.
         */
        'key' => 'cyrildewit.eloquent-viewable.cooldowns',

        'cache' => [

            /*
             * The cache store cooldowns are kept in when `store` is `cache`.
             * When `null`, the default cache store is used.
             */
            'store' => null,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Doctor
    |--------------------------------------------------------------------------
    |
    | `views:doctor` runs these checks and says what to fix.
    |
    */
    'doctor' => [

        /*
         * The checks to run, in order. Remove one to skip it, or list a
         * class of your own that implements `Doctor\Contracts\Check`.
         */
        'checks' => [
            SchemaCheck::class,
            IndexAdviceCheck::class,
            DimensionsCheck::class,
            SharedCacheCheck::class,
            ScheduleCheck::class,
            TrustedProxiesCheck::class,
            RedisStreamCheck::class,
            CrawlerShareCheck::class,
            ConfigurationCheck::class,
            // A check of the demo, see app/Viewable/Doctor/QueueBacklogCheck.php.
            QueueBacklogCheck::class,
        ],

        /*
         * Counts per day how many attempts are recorded and how many each
         * guard refuses, so the doctor can tell when `IgnoreCrawlers` refuses
         * more than `crawler_share` of them, from 0 to 1. Off by default,
         * because it adds two cache calls to every attempt.
         */
        'sample' => [

            'enabled' => true,

            'store' => null,

            'key' => 'cyrildewit.eloquent-viewable.samples',

            'crawler_share' => 0.5,

        ],

    ],

];
