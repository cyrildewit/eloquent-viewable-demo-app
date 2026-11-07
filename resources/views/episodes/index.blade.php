<x-layout title="Podcast">
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <p class="eyebrow">Inkwell Radio</p>
                <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">Conversations with people who ship</h1>
                <p class="mt-3 max-w-2xl text-stone-600">Episodes are the second viewable type in this demo. A page view lands in the default collection and a press on play in the <code class="font-mono text-sm">plays</code> collection, so one model has two kinds of views.</p>

                <div class="mt-8 space-y-4">
                    @foreach ($episodes as $episode)
                        <a href="{{ route('episodes.show', $episode) }}" class="panel group flex items-center gap-5 p-4 hover:border-stone-300">
                            <span class="size-20 shrink-0 rounded-xl sm:size-24" style="{{ $episode->cover() }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="text-xs font-medium text-stone-500">Episode {{ $episode->number }} · {{ $episode->published_at->format('j M Y') }} · {{ $episode->duration_minutes }} min</span>
                                <span class="mt-0.5 block font-serif text-xl font-semibold group-hover:text-accent">{{ $episode->title }}</span>
                                <span class="mt-1 block text-sm text-stone-600">with {{ $episode->guest }}. {{ $episode->summary }}</span>
                            </span>
                            <span class="hidden shrink-0 text-right text-sm sm:block tabular">
                                <span class="block font-semibold">▶ {{ Number::format($episode->plays) }}</span>
                                <span class="block text-xs text-stone-500">{{ Number::format($episode->total_views) }} views</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-stone-500">Plays and views are the counter columns <code class="font-mono">plays</code> (<code class="font-mono">['collection' => 'plays']</code>) and <code class="font-mono">total_views</code>.</p>
            </div>
            <aside>
                <x-panel title="Most played this month" call="views(Episode::class)->collection('plays')->period(Period::pastDays(30))->top(3)">
                    <x-ranking :ranking="$topPlayed" unit="plays" />
                </x-panel>
            </aside>
        </div>
    </div>
</x-layout>
