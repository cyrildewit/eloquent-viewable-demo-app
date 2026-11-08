<x-layout :title="$episode->title">
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end">
                    <div class="aspect-square w-48 shrink-0 rounded-2xl shadow-lg" style="{{ $episode->cover() }}"></div>
                    <div>
                        <p class="eyebrow">Episode {{ $episode->number }} · {{ $episode->duration_minutes }} min</p>
                        <h1 class="mt-1 font-serif text-4xl font-semibold leading-tight">{{ $episode->title }}</h1>
                        <p class="mt-2 text-stone-600">with {{ $episode->guest }}</p>
                    </div>
                </div>

                <div class="panel mt-8 p-5" data-player data-url="{{ route('episodes.play', $episode) }}">
                    <div class="flex items-center gap-4">
                        <button type="button" class="grid size-14 shrink-0 place-items-center rounded-full bg-ink text-white transition hover:scale-105" data-play aria-label="Play">
                            <svg class="ml-1 size-6" viewBox="0 0 20 20" fill="currentColor"><path d="M6.3 3.2A1.5 1.5 0 0 0 4 4.5v11a1.5 1.5 0 0 0 2.3 1.3l9-5.5a1.5 1.5 0 0 0 0-2.6l-9-5.5Z"/></svg>
                        </button>
                        <div class="min-w-0 flex-1">
                            <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full w-0 rounded-full bg-accent transition-all duration-[3000ms]" data-progress></div></div>
                            <p class="mt-2 text-xs text-stone-500" data-play-status>Press play to record a view in the <code class="font-mono">plays</code> collection.</p>
                        </div>
                    </div>
                    <x-call class="mt-4 border-t border-stone-100 pt-2" code="views($episode)->collection('plays')->queue()->attempt()" />
                </div>

                <p class="mt-8 max-w-2xl text-lg leading-relaxed text-stone-700">{{ $episode->summary }}</p>

                <x-panel class="mt-8" title="Plays per day, last 30 days" call="views($episode)->collection('plays')->period(Period::pastDays(30))->countByDay()">
                    <x-bars :series="$plays" tone="ink" height="h-32" />
                    <p class="mt-3 text-xs text-stone-500"><code class="font-mono">countByDay()</code> is a macro this demo adds to the <code class="font-mono">Views</code> class in AppServiceProvider.</p>
                </x-panel>
            </div>

            <aside class="space-y-5">
                <div class="panel p-5 text-sm">
                    <p class="eyebrow">How this page was recorded</p>
                    <p class="mt-2 text-stone-600">The route carries the <code class="font-mono">views</code> middleware. It records the bound episode after a successful <code class="font-mono">GET</code> response, so a 404 or a redirect records nothing. The controller has no <code class="font-mono">views()</code> call.</p>
                    <pre class="mt-3 overflow-x-auto rounded-lg bg-stone-50 p-3 font-mono text-[0.7rem] leading-relaxed">Route::get('/episodes/{episode}', ...)
    ->middleware(RecordViews::using(
        'episode', cooldown: 10,
    ));</pre>
                </div>

                <x-panel title="Views per collection" call="views($episode)->countByCollection()">
                    <dl class="space-y-2 text-sm">
                        @foreach ($collections as $name => $count)
                            <div class="flex justify-between"><dt class="font-mono text-xs">{{ $name === '' ? "'' (page views)" : "'{$name}'" }}</dt><dd class="font-semibold tabular">{{ Number::format($count) }}</dd></div>
                        @endforeach
                    </dl>
                </x-panel>

                <x-panel title="Listeners also read" call="views($episode)->alsoViewed(4, among: Post::class)">
                    <x-ranking :ranking="$alsoRead" unit="shared" />
                </x-panel>
            </aside>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const player = document.querySelector('[data-player]');
            const status = player.querySelector('[data-play-status]');

            player.querySelector('[data-play]').addEventListener('click', async () => {
                player.querySelector('[data-progress]').style.width = '100%';
                const response = await fetch(player.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
                const result = await response.json();
                status.innerHTML = result.skipped_by
                    ? `Skipped by <strong>${result.skipped_by.split('\\').pop()}</strong>.`
                    : result.queued
                        ? 'Queued: the <strong>evd-horizon</strong> container writes the play. Reload in a second to see the count.'
                        : 'Play recorded.';
            });
        </script>
    @endpush
</x-layout>
