<x-layout :title="'Stats · '.$post->title">
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="flex items-center gap-4">
                <span class="size-16 shrink-0 rounded-xl" style="{{ $post->cover() }}"></span>
                <div>
                    <p class="eyebrow">Stats for one article</p>
                    <h1 class="mt-1 font-serif text-3xl font-semibold tracking-tight"><a href="{{ route('posts.show', $post) }}" class="hover:text-accent">{{ $post->title }}</a></h1>
                    <x-call code="Route::get('/posts/{post}/stats/{period?}', fn (Post $post, ?Period $period) => …)" />
                </div>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach (['48h' => '48 hours', '7d' => '7 days', '30d' => '30 days', '3m' => '3 months', '1y' => '1 year', '2y' => '2 years'] as $key => $label)
                    <a href="{{ route('posts.stats', [$post, $key]) }}" @class(['rounded-full border px-3 py-1.5 text-sm', 'border-ink bg-ink text-white' => $periodKey === $key, 'border-stone-200 bg-white text-stone-600' => $periodKey !== $key])>{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Views" :value="$views->current" :comparison="$views" call="views($post)->period($period)->compare()" />
            <x-stat label="Unique visitors" :value="$visitors->current" :comparison="$visitors" call="->unique()->compare()" />
            <x-stat label="Came back on another day" :value="$returning->current" :comparison="$returning" call="->returning()->compare()" />
            <x-stat label="Returning share" :value="$frequency->returningShare() === null ? '–' : Number::percentage($frequency->returningShare() * 100, maxPrecision: 1)" :hint="Number::format($frequency->returning()).' of '.Number::format($frequency->total()).' visitors viewed on two days or more.'" call="->countByFrequency()->returningShare()" />
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <x-panel class="lg:col-span-2" :title="'Views per '.$granularity->value" :call="'->countByInterval(Granularity::'.ucfirst($granularity->value).')'">
                <x-bars :series="$series" height="h-48" />
            </x-panel>
            <x-panel title="The last 48 hours" call="views($post)->period(Period::subHours(48))->countByInterval(Granularity::Hour)">
                <x-bars :series="$hours" tone="ink" height="h-48" />
            </x-panel>
        </div>

        <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <x-panel title="Sources" call="->countBy('source', limit: 8)"><x-split :counts="$sources" /></x-panel>
            <x-panel title="Referring hosts" call="->countBy('referrer_host', limit: 6)"><x-split :counts="$referrers" empty="Every view came without an external referrer." /></x-panel>
            <x-panel title="Countries" call="->countBy('country', limit: 8)"><x-split :counts="$countries" /></x-panel>
            <x-panel title="Devices" call="->countBy('device')"><x-split :counts="$devices" /></x-panel>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <x-panel title="Search engines, by device" call="->whereDimension('source', ['Google', 'Bing', 'DuckDuckGo'])->countBy('device')">
                <x-split :counts="$googleMobile" />
            </x-panel>

            <x-panel title="Visits per visitor" call="->countByFrequency(upTo: 5)">
                @php($freq = $frequency->toArray())
                @php($freqMax = max(1, ...array_values($freq ?: [0])))
                <div class="flex h-32 items-end gap-2">
                    @foreach ($freq as $days => $count)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <span class="text-[0.65rem] text-stone-500 tabular">{{ Number::abbreviate($count) }}</span>
                            <div class="w-full rounded-t bg-accent/75" style="height: {{ max(2, $count / $freqMax * 100) }}px"></div>
                            <span class="text-xs text-stone-600">{{ $days }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-center text-[0.68rem] text-stone-500">days with a view, per visitor</p>
            </x-panel>

            <x-panel title="The last closed hour against past weeks" call="->period($lastHour)->againstBaseline(Seasonality::Week)">
                <div class="flex items-end gap-2">
                    @php($refMax = max(1, $baseline->current, ...$baseline->references))
                    @foreach ($baseline->references as $i => $reference)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <span class="text-[0.65rem] text-stone-500 tabular">{{ $reference }}</span>
                            <div class="w-full rounded-t bg-stone-300" style="height: {{ max(2, $reference / $refMax * 80) }}px"></div>
                            <span class="text-[0.65rem] text-stone-500">−{{ count($baseline->references) - $i }}w</span>
                        </div>
                    @endforeach
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <span class="text-[0.65rem] font-semibold tabular">{{ $baseline->current }}</span>
                        <div class="w-full rounded-t bg-accent" style="height: {{ max(2, $baseline->current / $refMax * 80) }}px"></div>
                        <span class="text-[0.65rem] font-semibold">now</span>
                    </div>
                </div>
                <p class="mt-3 text-sm tabular">mean {{ Number::format($baseline->mean, maxPrecision: 1) }} · z-score <span class="font-semibold">{{ Number::format($baseline->zScore(), maxPrecision: 2) }}</span></p>
            </x-panel>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <x-panel title="Newsletter reach by campaign" call="views($post)->rollup('newsletter')->countByDimension()">
                <dl class="space-y-1.5 text-sm">
                    @forelse ($newsletter as $campaign => $count)
                        <div class="flex justify-between"><dt class="font-mono text-xs">{{ $campaign === '' ? '(none)' : $campaign }}</dt><dd class="font-semibold tabular">{{ Number::format($count) }}</dd></div>
                    @empty
                        <p class="text-stone-500">No newsletter readers.</p>
                    @endforelse
                </dl>
                <p class="mt-3 text-xs text-stone-500">A custom rollup of this demo, <code class="font-mono">App\Viewable\Rollups\NewsletterViews</code>. Once folded, it keeps these numbers after the views are pruned.</p>
            </x-panel>

            <x-panel title="Counter columns and fresh counts" call="views:recount writes querying.counters">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-stone-500"><tr><th class="pb-2 font-medium">column</th><th class="pb-2 text-right font-medium">column</th><th class="pb-2 text-right font-medium">now</th></tr></thead>
                    <tbody class="tabular">
                        @foreach ($live as $column => $fresh)
                            <tr class="border-t border-stone-100">
                                <td class="py-1.5 font-mono text-xs">{{ $column }}</td>
                                <td class="py-1.5 text-right">{{ Number::format($post->{$column}) }}</td>
                                <td @class(['py-1.5 text-right', 'font-semibold text-amber-600' => $fresh !== (int) $post->{$column}])>{{ Number::format($fresh) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-stone-100"><td class="py-1.5 font-mono text-xs">hot_score</td><td class="py-1.5 text-right">{{ Number::format($post->hot_score, maxPrecision: 2) }}</td><td></td></tr>
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-stone-500">An amber number means views arrived since the last recount. Run <code class="font-mono">views:recount</code> on the <a class="text-accent" href="{{ route('console') }}">Console</a>.</p>
            </x-panel>

            <x-panel title="Milestones and spikes" call="view_milestones · view_spikes">
                @forelse ($milestones as $mark)
                    <p class="flex justify-between text-sm"><span class="font-mono text-xs">{{ $mark->column }}</span><span>marked at <span class="font-semibold tabular">{{ Number::format($mark->high_water) }}</span></span></p>
                @empty
                    <p class="text-sm text-stone-500">No milestone marks yet.</p>
                @endforelse
                <div class="mt-3 border-t border-stone-100 pt-3">
                    @forelse ($spikes as $spike)
                        <p class="text-sm"><span @class(['font-semibold', 'text-emerald-700' => $spike->direction === 'spike', 'text-rose-700' => $spike->direction === 'drop'])>{{ ucfirst($spike->direction) }}</span> since {{ \Carbon\Carbon::parse($spike->since)->diffForHumans() }}, peak {{ $spike->peak_count }} views (z {{ Number::format($spike->peak_score, maxPrecision: 1) }}){{ $spike->quiet_since ? ', quiet since '.\Carbon\Carbon::parse($spike->quiet_since)->diffForHumans() : '' }}</p>
                    @empty
                        <p class="text-sm text-stone-500">Not spiking or dropping.</p>
                    @endforelse
                </div>
                <p class="mt-3 text-xs text-stone-500">The high-water mark keeps a milestone from firing twice. A row in <code class="font-mono">view_spikes</code> lives while the post is out of its baseline.</p>
            </x-panel>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <x-panel class="lg:col-span-2" title="Readers also read" call="views($post)->alsoViewed(5, among: Post::class)">
                <x-ranking :ranking="$alsoRead" unit="shared readers" />
            </x-panel>
            <div class="space-y-5">
                <x-panel title="…and listened to" call="views($post)->alsoViewed(3, among: Episode::class)">
                    <x-ranking :ranking="$alsoListened" unit="shared" />
                </x-panel>
                <x-panel title="Collections" call="->countByCollection()">
                    <dl class="space-y-1.5 text-sm">
                        @foreach ($collections as $name => $count)
                            <div class="flex justify-between"><dt class="font-mono text-xs">{{ $name === '' ? "''" : "'{$name}'" }}</dt><dd class="font-semibold tabular">{{ Number::format($count) }}</dd></div>
                        @endforeach
                    </dl>
                    <p class="mt-2 text-xs text-stone-500"><code class="font-mono">feed</code> views come from RSS readers, <code class="font-mono">amp</code> from the cached lite edition.</p>
                </x-panel>
            </div>
        </div>
    </div>
</x-layout>
