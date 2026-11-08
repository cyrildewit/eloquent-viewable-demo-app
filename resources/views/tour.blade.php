<x-layout title="Tour">
    @php
        $firstPost = \App\Models\Post::query()->published()->orderByDesc('total_views')->first();
        $sections = [
            'Recording' => [
                ['Record in a controller, and see why', 'views($post)->cooldown(5)->attempt()', route('posts.show', $firstPost), 'Open any article. The receipt shows the RecordResult; reload to watch the cooldown skip it.'],
                ['Record from a route', "->middleware(RecordViews::using('episode', cooldown: 10))", route('episodes.index'), 'Episode pages have no views() call; the route middleware records them.'],
                ['Record from the browser', "@viewsBeacon(\$post, collection: 'amp', live: true)", route('posts.lite', $firstPost), 'A page from a full-page cache, recorded by the beacon.'],
                ['Queued recording and collections', "views(\$episode)->collection('plays')->queue()->attempt()", route('episodes.show', \App\Models\Episode::query()->published()->latest('published_at')->first()), 'Press play: the evd-horizon container writes the view.'],
                ['Every guard, one click away', 'views($post)->useVisitor($visitor)->attempt()', route('lab'), 'Crawlers, ignored IPs, prefetches, opted-out readers, authors, bursts, cooldowns.'],
                ['Dimensions from the request', "views(\$post)->countBy('source')", route('posts.show', [$firstPost, 'utm_source' => 'nl', 'utm_medium' => 'email', 'utm_campaign' => 'launch-week']), 'Arrive with UTM tags and read the resolved dimensions on the receipt.'],
            ],
            'Counting' => [
                ['Totals, uniques, comparisons', '->period($period)->compare()', route('stats', '30d'), 'The KPI tiles on Stats, each compared with the period before.'],
                ['Charts per interval, in any timezone', '->countByInterval(Granularity::Day)', route('stats', '30d').'?tz=Australia/Sydney', 'Change the timezone and the buckets move with it.'],
                ['Periods from the URL', "Period::parse('2026-01-01..2026-02-01')", route('stats', '12h'), 'Period binds as a route parameter.'],
                ['New and returning visitors', '->returning()->compare(); ->countByFrequency()', route('stats', '30d'), 'Returning visitors and visits per visitor.'],
                ['Counts of a page of models', 'Views::forViewables($posts)->counts()', route('posts.index'), 'One query for the weekly numbers of the whole page.'],
                ['Ordering and filtering by views', "Post::orderByViews(); Post::whereViewsCount('>=', 1000)", route('posts.index', ['sort' => 'club']), 'Every sort tab is a scope.'],
            ],
            'Rankings' => [
                ['Most viewed, across types', 'Views::period(Period::pastDays(7))->top(5)', route('home'), 'Articles and episodes in one ranking.'],
                ['Trending, under five curves', 'views(Post::class)->trending(curve: new LinearDecay(...))', route('stats').'#curves', 'Including a curve of this demo.'],
                ['Hot, Reddit style', 'Post::orderByHot()', route('home'), 'The front page grid, from a counter column.'],
                ['Rising, spikes and drops', '->rising(); ->anomalies(); ->againstBaseline()', route('stats').'#anomalies', 'Simulate a spike or a drop on the Console first.'],
                ['Readers also read', "views(\$post)->alsoViewed(4, among: Post::class)", route('posts.show', $firstPost), 'Below every article, from the pairs table.'],
                ['Recommended for you', '$user->recommended(Post::class)', route('account'), 'Sign in as Chloe or Alice, with the reason for every pick.'],
                ['Who is reading right now', 'views($post)->live()->viewers(); Views::live()->top()', route('stats').'#live', 'Open an article in two browsers, or simulate readers.'],
            ],
            'Maintenance' => [
                ['Rollups that outlive pruning', 'php artisan views:maintain', route('console').'#timeline', 'Run it, then compare the two year chart before and after.'],
                ['Retention: anonymise and prune', 'views:anonymise --dry-run; views:prune', route('console').'#commands', 'Every command has a dry run and a diff of the tables.'],
                ['Counter columns and milestones', 'views:recount → ViewMilestoneReached', route('console').'#simulate', 'Carry a post past a milestone, recount, read the event.'],
                ['Spike alerts', 'views:detect-spikes → ViewsSpiked', route('console').'#simulate', 'Simulate a spike, detect, and watch the event feed.'],
                ['Purging bot views', 'views:purge-bots --since=1d --whole-visitor', route('console').'#simulate', 'Simulate a scraper, then purge it.'],
                ['A custom rollup', "views(\$post)->rollup('newsletter')->countByDimension()", route('posts.stats', [$firstPost, '1y']), 'Newsletter reach per campaign, kept forever.'],
                ['Checking the setup', 'php artisan views:doctor', route('console').'#doctor', 'Including a check of this demo.'],
            ],
            'Privacy' => [
                ['Opting out', 'ViewerCanOptOut::tracksViews()', route('account'), 'Dan opted out; toggle it for any reader.'],
                ['Export, anonymise, forget', '$user->forgetViewHistory(includeGuestViews: true)', route('account'), 'The GDPR toolkit, with events for an audit log.'],
                ['Without a cookie', "VIEWABLE_IDENTITY=fingerprint", route('console').'#config', 'Switch the identity in .env and restart.'],
            ],
        ];
    @endphp
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <p class="eyebrow">Tour</p>
        <h1 class="mt-1 max-w-3xl font-serif text-4xl font-semibold leading-tight tracking-tight">Every feature of Eloquent Viewable 9, and where to try it</h1>
        <p class="mt-3 max-w-3xl text-stone-600">Inkwell is a small publication with simulated traffic: {{ Number::format(views(\App\Models\Post::class)->count() + views(\App\Models\Episode::class)->count()) }} views of {{ \App\Models\Post::count() }} articles and {{ \App\Models\Episode::count() }} podcast episodes by {{ \App\Models\User::count() }} readers. Every panel in the app names the call it makes.</p>

        <div class="mt-10 space-y-12">
            @foreach ($sections as $section => $items)
                <section>
                    <h2 class="font-serif text-2xl font-semibold">{{ $section }}</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($items as [$title, $call, $url, $hint])
                            <a href="{{ $url }}" class="panel group flex flex-col p-5 hover:border-stone-300">
                                <p class="font-medium group-hover:text-accent">{{ $title }} →</p>
                                <p class="mt-1 flex-1 text-sm text-stone-600">{{ $hint }}</p>
                                <x-call :code="$call" class="mt-3 border-t border-stone-100 pt-2" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layout>
