<x-layout :title="$post->title">
    <article>
        <header class="relative">
            <div class="h-72 sm:h-96" style="{{ $post->cover() }}"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/65 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0">
                <div class="mx-auto max-w-7xl px-4 pb-8 sm:px-6">
                    <a href="{{ route('posts.index') }}" class="chip border-white/30 bg-white/15 text-white backdrop-blur">{{ \App\Demo\Catalog::TOPICS[$post->topic][0] }}</a>
                    <h1 class="mt-3 max-w-4xl font-serif text-4xl font-semibold leading-tight text-white sm:text-5xl">{{ $post->title }}</h1>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-white/85">
                        <x-avatar :user="$post->author" class="size-8 text-xs ring-2 ring-white/40" />
                        <span>{{ $post->author->name }}</span>
                        <span>·</span>
                        <span>{{ $post->published_at->format('j F Y') }}</span>
                        <span>·</span>
                        <span>{{ $post->reading_minutes }} min read</span>
                        <span>·</span>
                        <span class="tabular">{{ Number::format($total) }} views</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-10 px-4 pt-10 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0">
                @if ($lastVisit)
                    <p class="mb-6 rounded-xl bg-accent-soft px-4 py-2.5 text-sm text-ink">
                        Welcome back. You last opened this {{ $lastVisit->diffForHumans() }}.
                        <x-call class="!inline" code="$user->lastViewedAt($post)" />
                    </p>
                @endif

                <p class="mb-8 font-serif text-xl leading-relaxed text-stone-800">{{ $post->excerpt }}</p>
                <div class="prose-ink max-w-[68ch]">
                    @foreach (preg_split("/\n\n+/", $post->body) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>

                <section class="mt-14 border-t border-stone-200 pt-8">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-serif text-2xl font-semibold">Readers also read</h2>
                        <x-call code="views($post)->alsoViewed(4, among: Post::class)" />
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        @forelse ($alsoRead as $entry)
                            <a href="{{ route('posts.show', $entry->viewable) }}" class="panel group flex gap-4 p-3 hover:border-stone-300">
                                <span class="size-16 shrink-0 rounded-lg" style="{{ $entry->viewable->cover() }}"></span>
                                <span class="min-w-0">
                                    <span class="block font-medium leading-snug group-hover:text-accent">{{ $entry->viewable->title }}</span>
                                    <span class="mt-1 block text-xs text-stone-500 tabular">{{ Number::format($entry->count) }} readers read both</span>
                                </span>
                            </a>
                        @empty
                            <p class="text-sm text-stone-500">Not enough shared readers yet: a pair needs {{ config('eloquent-viewable.querying.also_viewed.minimum_visitors') }} visitors in common.</p>
                        @endforelse
                    </div>
                    @if ($alsoListened->count())
                        <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
                            <span class="text-stone-500">Readers of this post also listened to</span>
                            @foreach ($alsoListened as $entry)
                                <a href="{{ route('episodes.show', $entry->viewable) }}" class="chip hover:border-stone-300">▶ {{ $entry->viewable->title }}</a>
                            @endforeach
                            <x-call code="views($post)->alsoViewed(2, among: Episode::class)" />
                        </div>
                    @endif
                    <p class="mt-4 text-xs text-stone-500">
                        @if (config('eloquent-viewable.querying.pairs.enabled'))
                            Read from the <code class="font-mono">view_pairs</code> table that <code class="font-mono">views:pairs</code> rewrites, so new views show up after its next run.
                        @else
                            Read from the views table on every call.
                        @endif
                    </p>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-20 lg:self-start">
                <x-receipt :result="$result" />

                <div class="panel p-5" data-live data-url="{{ route('posts.heartbeat', $post) }}" data-every="{{ $heartbeat }}">
                    <div class="flex items-center justify-between">
                        <p class="eyebrow">Reading now</p>
                        <span class="live-dot size-2 rounded-full bg-emerald-500"></span>
                    </div>
                    <p class="mt-2 font-serif text-3xl font-semibold tabular"><span data-live-count>{{ $active ?? '–' }}</span> <span class="font-sans text-sm font-normal text-stone-500">active visitors</span></p>
                    <div class="mt-3 flex -space-x-2" data-live-viewers></div>
                    <p class="mt-1 hidden text-xs text-stone-500" data-live-names></p>
                    <x-call class="mt-3 border-t border-stone-100 pt-2" code="views($post)->heartbeat(); views($post)->live()->viewers(4)" />
                    <p class="mt-2 text-xs text-stone-500">This page sends a heartbeat every {{ $heartbeat }}s while the tab is visible. Open it in a private window to see the count go up.</p>
                </div>

                <div class="panel p-5">
                    <div class="flex items-baseline justify-between">
                        <p class="eyebrow">Last 30 days</p>
                        <a href="{{ route('posts.stats', [$post, '30d']) }}" class="text-xs font-medium text-accent">Full stats →</a>
                    </div>
                    <x-spark :values="$series->values()" class="mt-3 h-14 w-full" />
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div><dt class="text-[0.68rem] text-stone-500">Views</dt><dd class="font-semibold tabular">{{ Number::abbreviate($series->total(), maxPrecision: 1) }}</dd></div>
                        <div><dt class="text-[0.68rem] text-stone-500">Unique</dt><dd class="font-semibold tabular">{{ Number::abbreviate($unique, maxPrecision: 1) }}</dd></div>
                        <div><dt class="text-[0.68rem] text-stone-500">7d vs prior</dt><dd @class(['font-semibold tabular', 'text-emerald-600' => $week->delta >= 0, 'text-rose-600' => $week->delta < 0])>{{ $week->percent === null ? 'new' : ($week->delta >= 0 ? '+' : '').Number::format($week->percent, maxPrecision: 0).'%' }}</dd></div>
                    </dl>
                    <div class="mt-4 border-t border-stone-100 pt-4">
                        <p class="mb-2 text-xs text-stone-500">Where readers came from</p>
                        <x-split :counts="$sources" />
                    </div>
                </div>

                <div class="panel p-5 text-sm">
                    <p class="eyebrow">Try it</p>
                    <ul class="mt-2 space-y-2 text-stone-600">
                        <li>Reload: the cooldown skips the view for 5 minutes.</li>
                        <li><a class="text-accent" href="{{ route('posts.show', [$post, 'utm_source' => 'nl', 'utm_medium' => 'email', 'utm_campaign' => 'Launch-Week']) }}">Arrive from the newsletter</a> and watch the dimensions change.</li>
                        <li>Sign in as {{ $post->author->name }} and the demo's IgnoreAuthors guard skips you.</li>
                        <li><a class="text-accent" href="{{ route('posts.lite', $post) }}">Open the cached lite edition</a>, recorded by the beacon.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </article>

    @push('scripts')
        <script type="module">
            const panel = document.querySelector('[data-live]');
            const token = document.querySelector('meta[name=csrf-token]').content;

            async function beat() {
                if (document.visibilityState !== 'visible') return;
                const response = await fetch(panel.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
                if (! response.ok) return;
                const data = await response.json();
                panel.querySelector('[data-live-count]').textContent = data.active;
                panel.querySelector('[data-live-viewers]').innerHTML = data.viewers.map(v =>
                    `<span title="${v.name}" class="grid size-7 place-items-center rounded-full text-[0.6rem] font-semibold text-white ring-2 ring-white" style="background: oklch(0.6 0.15 ${v.hue})">${v.initials}</span>`).join('');
                const names = panel.querySelector('[data-live-names]');
                names.textContent = data.viewers.length ? data.viewers.map(v => v.name.split(' ')[0]).join(', ') + ' signed in' : '';
                names.classList.toggle('hidden', ! data.viewers.length);
            }

            beat();
            setInterval(beat, panel.dataset.every * 1000);
            document.addEventListener('visibilitychange', beat);
        </script>
    @endpush
</x-layout>
