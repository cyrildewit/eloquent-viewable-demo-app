<x-layout title="Stats">
    @php
        $typeLabel = $type === 'post' ? 'Post' : 'Episode';
        $query = fn (array $merge = []) => array_filter(array_merge(['type' => $type, 'tz' => $timezone === 'UTC' ? null : $timezone], $merge));
        $dimensionTitles = ['source' => 'Source', 'medium' => 'Medium', 'device' => 'Device', 'country' => 'Country', 'campaign' => 'Newsletter campaign', 'plan' => 'Reader plan'];
    @endphp

    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="eyebrow">Stats</p>
                <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">{{ $type === 'post' ? 'Articles' : 'Podcast' }}, {{ strtolower(\App\Http\Controllers\StatsController::PERIODS[$periodKey] ?? $periodKey) }}</h1>
                <p class="mt-2 text-sm text-stone-500 tabular">
                    {{ $period->getStartDateTime()?->timezone($timezone)->format('j M Y, H:i') }} – {{ $period->endOrNow()->timezone($timezone)->format('j M Y, H:i') }} {{ $timezone }}.
                    Counts come from the <code class="font-mono">{{ config('eloquent-viewable.querying.source.driver') }}</code> source.
                </p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-full border border-stone-200 bg-white p-0.5 text-sm">
                    @foreach (['post' => 'Articles', 'episode' => 'Podcast'] as $key => $label)
                        <a href="{{ route('stats', [$periodKey, ...$query(['type' => $key])]) }}" @class(['rounded-full px-3 py-1', 'bg-ink text-white' => $type === $key, 'text-stone-600' => $type !== $key])>{{ $label }}</a>
                    @endforeach
                </div>
                <input type="hidden" name="type" value="{{ $type }}">
                <select name="tz" class="input !w-auto !rounded-full !py-1.5" onchange="this.form.submit()" title="->timezone() moves bucket boundaries to that timezone's midnight">
                    @foreach (\App\Http\Controllers\StatsController::TIMEZONES as $tz)
                        <option value="{{ $tz }}" @selected($tz === $timezone)>{{ $tz }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-1.5">
            @foreach (\App\Http\Controllers\StatsController::PERIODS as $key => $label)
                <a href="{{ route('stats', [$key, ...$query()]) }}" @class([
                    'rounded-full border px-3.5 py-1.5 text-sm',
                    'border-ink bg-ink text-white' => $periodKey === $key,
                    'border-stone-200 bg-white text-stone-600 hover:border-stone-300' => $periodKey !== $key,
                ])>{{ $label }}</a>
            @endforeach
            <span class="ml-2 text-xs text-stone-500">or any <code class="font-mono">Period::parse()</code> form in the URL: <a class="font-mono text-accent" href="{{ route('stats', ['12h', ...$query()]) }}">/stats/12h</a>, <a class="font-mono text-accent" href="{{ route('stats', [now()->subMonths(2)->startOfMonth()->format('Y-m-d').'..'.now()->subMonth()->startOfMonth()->format('Y-m-d'), ...$query()]) }}">/stats/{{ now()->subMonths(2)->startOfMonth()->format('Y-m-d') }}..{{ now()->subMonth()->startOfMonth()->format('Y-m-d') }}</a></span>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Views" :value="$views->current" :comparison="$views" :call="'views('.$typeLabel.'::class)->period($period)->compare()'" />
            <x-stat label="Unique visitors" :value="$visitors->current" :comparison="$visitors" call="->unique()->compare()" />
            <x-stat label="Returning visitors" :value="$returning->current" :comparison="$returning" call="->returning()->compare()" />
            @if ($googleMobile instanceof \Throwable)
                <x-stat label="Google, on a phone" value="–" hint="Two dimensions at once can only be counted while the views table holds the whole period. Pick 3 months or less." call="->whereDimension('source', 'Google')->whereDimension('device', 'mobile')->count()" />
            @else
                <x-stat label="Google, on a phone" :value="$googleMobile" hint="Views narrowed by two dimensions at once." call="->whereDimension('source', 'Google')->whereDimension('device', 'mobile')->count()" />
            @endif
        </div>

        <x-panel class="mt-5" :title="'Views per '.$granularity->value" :call="'->countByInterval(Granularity::'.ucfirst($granularity->value).')'">
            <x-slot:actions>
                <span class="text-xs text-stone-500 tabular">
                    total {{ Number::format($series->total()) }} · average {{ Number::format($series->average(), maxPrecision: 1) }}
                    @if ($series->peak()) · <span class="font-medium text-amber-600">peak {{ $series->peak()->label }} ({{ Number::format($series->peak()->count) }})</span>@endif
                </span>
            </x-slot:actions>
            <x-bars :series="$series" height="h-56" />
            @if (config('eloquent-viewable.querying.source.driver') === 'rollup')
                <p class="mt-4 text-xs text-stone-500">With the <code class="font-mono">rollup</code> source, buckets the views table no longer holds come from the rollup tiers. Run <code class="font-mono">views:maintain</code> on the <a href="{{ route('console') }}" class="text-accent">Console</a>, which prunes views older than {{ config('eloquent-viewable.retention.prune.after') }}, then come back to the 2 year chart: it does not change.</p>
            @endif
        </x-panel>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <x-panel :title="'Top '.Str::plural(strtolower($typeLabel))" :call="'views('.$typeLabel.'::class)->period($period)->top(8)'" class="lg:col-span-2">
                <x-ranking :ranking="$top" />
            </x-panel>
            <div class="space-y-5">
                <x-panel title="Across every type" call="Views::period($period)->top(5)">
                    <x-ranking :ranking="$crossType" />
                </x-panel>
                <x-panel title="Collections" call="->countByCollection()">
                    <dl class="space-y-1.5 text-sm">
                        @forelse ($collections as $name => $count)
                            <div class="flex justify-between gap-3"><dt class="font-mono text-xs">{{ $name === '' ? "'' none" : "'{$name}'" }}</dt><dd class="font-semibold tabular">{{ Number::format($count) }}</dd></div>
                        @empty
                            <p class="text-stone-500">No views in this period.</p>
                        @endforelse
                    </dl>
                </x-panel>
            </div>
        </div>

        <h2 class="mt-12 font-serif text-2xl font-semibold">Where readers come from</h2>
        <p class="mt-1 text-sm text-stone-500">Each dimension is a column on the views table, resolved from the request when the view was recorded. <code class="font-mono">plan</code> is a dimension of this demo, kept in <code class="font-mono">context-&gt;plan</code>.</p>
        <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($dimensions as $dimension => $counts)
                <x-panel :title="$dimensionTitles[$dimension]" :call="'->countBy(\''.$dimension.'\', limit: 6)'">
                    <x-split :counts="$counts" />
                </x-panel>
            @endforeach
        </div>

        <div class="mt-12 grid gap-5 lg:grid-cols-3">
            <x-panel title="New and returning" call="->countByFrequency(upTo: 4)">
                @php($freq = $frequency->toArray())
                @php($freqMax = max(1, ...array_values($freq ?: [0])))
                <div class="space-y-2">
                    @foreach ($freq as $days => $visitorsOnDays)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-14 shrink-0 text-xs text-stone-500">{{ $days }} {{ $days === 1 ? 'day' : 'days' }}</span>
                            <div class="h-5 flex-1 overflow-hidden rounded bg-stone-100"><div class="h-full rounded bg-accent/75" style="width: {{ $visitorsOnDays / $freqMax * 100 }}%"></div></div>
                            <span class="w-14 shrink-0 text-right tabular">{{ Number::format($visitorsOnDays) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-sm tabular">
                    <span class="font-semibold">{{ $frequency->returningShare() === null ? '–' : Number::percentage($frequency->returningShare() * 100, maxPrecision: 1) }}</span>
                    <span class="text-stone-500">of {{ Number::format($frequency->total()) }} visitors came back on another day.</span>
                </p>
                <p class="mt-2 text-xs text-stone-500">Anonymised views are left out: their visitor ids no longer link two days.</p>
            </x-panel>

            <x-panel title="Rising against yesterday" call="->period(Period::pastDays(1))->rising(5)">
                <x-ranking :ranking="$rising" score empty="Nothing grew against the day before." />
                <p class="mt-3 text-xs text-stone-500">Score = views today ÷ views the day before.</p>
            </x-panel>

            <x-panel title="Unusual for a {{ now()->subDay()->format('l') }}" call="->anomalies(seasonality: Seasonality::Day, samples: 7)">
                <x-ranking :ranking="$dailySeason" score empty="Yesterday looked like the week before it." />
                <p class="mt-3 text-xs text-stone-500">Yesterday against the 7 days before, scored as a z-score.</p>
            </x-panel>
        </div>

        <x-panel class="mt-5" title="The last closed hour against the same hour on the past 4 weeks" call="->period(Period::create(now()->subHour()->startOfHour(), now()->startOfHour()))->anomalies()" id="anomalies">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">Spiking, threshold 2.5</p>
                    @forelse ($spikes as $entry)
                        <div class="flex items-center justify-between gap-3 border-b border-stone-100 py-2 text-sm last:border-0">
                            <a href="{{ route('posts.stats', [$entry->viewable, '7d']) }}" class="truncate font-medium hover:text-accent">{{ $entry->viewable->title }}</a>
                            <span class="shrink-0 text-xs text-stone-500 tabular">{{ $entry->count }} vs [{{ implode(', ', $entry->baseline->references) }}] · z {{ Number::format($entry->score, maxPrecision: 1) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">Nothing unusual. Simulate a spike on the <a class="text-accent" href="{{ route('console') }}#simulate">Console</a>.</p>
                    @endforelse
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-700">Dropping, threshold −2.5</p>
                    @forelse ($drops as $entry)
                        <div class="flex items-center justify-between gap-3 border-b border-stone-100 py-2 text-sm last:border-0">
                            <span class="truncate font-medium">{{ $entry->viewable->title }}</span>
                            <span class="shrink-0 text-xs text-stone-500 tabular">{{ $entry->count }} vs [{{ implode(', ', $entry->baseline->references) }}] · z {{ Number::format($entry->score, maxPrecision: 1) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">No drops. A drop needs a baseline of about ten views an hour; simulate one on the Console.</p>
                    @endforelse
                </div>
            </div>
            <p class="mt-4 text-xs text-stone-500"><code class="font-mono">views:detect-spikes</code> runs the same comparison on a schedule and fires <code class="font-mono">ViewsSpiked</code>, <code class="font-mono">ViewsDropped</code> and <code class="font-mono">ViewsSettled</code> once each.</p>
        </x-panel>

        <h2 id="curves" class="mt-12 scroll-mt-20 font-serif text-2xl font-semibold">Trending, five ways</h2>
        <p class="mt-1 max-w-3xl text-sm text-stone-500">The same views ranked under different decay curves. A short half-life reacts fast and forgets fast; a window counts every view the same. The last one is a curve of this demo, <code class="font-mono">App\Viewable\Curves\PlateauDecay</code>.</p>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach ($curves as $label => [$call, $ranking])
                <x-panel :title="$label" :call="$call">
                    <ol class="space-y-2.5">
                        @forelse ($ranking as $entry)
                            <li class="text-sm">
                                <a href="{{ route($type === 'post' ? 'posts.show' : 'episodes.show', $entry->viewable) }}" class="line-clamp-2 font-medium leading-snug hover:text-accent">{{ $entry->rank }}. {{ $entry->viewable->title }}</a>
                                <span class="text-xs text-stone-500 tabular">score {{ Number::format($entry->score, maxPrecision: 1) }} · {{ Number::format($entry->count) }} views</span>
                            </li>
                        @empty
                            <li class="text-sm text-stone-500">Nothing yet.</li>
                        @endforelse
                    </ol>
                </x-panel>
            @endforeach
        </div>

        <x-panel class="mt-12" title="Live" call="Views::live()->count(); Views::live()->top(6); views(Post::class)->live()->count()" id="live">
            @if ($live === null)
                <p class="text-sm text-rose-600">Presence needs Redis, which did not answer.</p>
            @else
                <div class="grid gap-6 md:grid-cols-[14rem_minmax(0,1fr)]" data-live-stats data-url="{{ route('stats.live') }}">
                    <div class="space-y-3">
                        <div>
                            <p class="font-serif text-5xl font-semibold tabular" data-k="count">{{ $live['count'] }}</p>
                            <p class="text-xs text-stone-500">visitors in the last {{ config('eloquent-viewable.presence.window') }}s</p>
                        </div>
                        <dl class="grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="rounded-lg bg-stone-50 py-2"><dd class="text-base font-semibold tabular" data-k="minute">–</dd><dt class="text-stone-500">last 60s</dt></div>
                            <div class="rounded-lg bg-stone-50 py-2"><dd class="text-base font-semibold tabular" data-k="posts">–</dd><dt class="text-stone-500">articles</dt></div>
                            <div class="rounded-lg bg-stone-50 py-2"><dd class="text-base font-semibold tabular" data-k="episodes">–</dd><dt class="text-stone-500">episodes</dt></div>
                        </dl>
                        <form method="POST" action="{{ route('console.simulate') }}">
                            @csrf
                            <input type="hidden" name="action" value="live">
                            <button class="btn btn-sm w-full">Simulate 24 readers</button>
                        </form>
                    </div>
                    <ol class="divide-y divide-stone-100" data-live-top>
                        @foreach ($live['top'] as $entry)
                            <li class="flex justify-between py-2 text-sm"><span class="truncate">{{ $entry->viewable->title }}</span><span class="font-semibold tabular">{{ $entry->count }}</span></li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </x-panel>
    </div>

    @push('scripts')
        <script type="module">
            const root = document.querySelector('[data-live-stats]');

            async function poll() {
                if (! root || document.visibilityState !== 'visible') return;
                const data = await (await fetch(root.dataset.url, { headers: { Accept: 'application/json' } })).json();
                for (const key of ['count', 'minute', 'posts', 'episodes']) {
                    root.querySelector(`[data-k=${key}]`).textContent = data[key];
                }
                root.querySelector('[data-live-top]').innerHTML = data.top.map(e =>
                    `<li class="flex justify-between py-2 text-sm"><span class="truncate">${e.viewable.title}</span><span class="font-semibold tabular">${e.count}</span></li>`).join('')
                    || '<li class="py-2 text-sm text-stone-500">Nobody has a page open right now.</li>';
                document.querySelectorAll('[data-nav-live]').forEach(el => el.textContent = data.count);
            }

            poll();
            setInterval(poll, 5000);
        </script>
    @endpush
</x-layout>
