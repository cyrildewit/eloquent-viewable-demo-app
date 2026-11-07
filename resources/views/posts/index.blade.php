<x-layout title="Articles">
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <p class="eyebrow">Articles</p>
        <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">{{ $sorts[$sort][0] }}</h1>

        <div class="mt-6 flex gap-1.5 overflow-x-auto pb-1">
            @foreach ($sorts as $key => [$label])
                <a href="{{ route('posts.index', ['sort' => $key]) }}" @class([
                    'shrink-0 rounded-full border px-3.5 py-1.5 text-sm transition',
                    'border-ink bg-ink text-white' => $sort === $key,
                    'border-stone-200 bg-white text-stone-600 hover:border-stone-300' => $sort !== $key,
                ])>{{ $label }}</a>
            @endforeach
        </div>
        <div class="mt-3 rounded-xl border border-stone-200 bg-white px-4 py-2.5">
            <x-call :code="$sorts[$sort][1].'->paginate(9)'" />
            @if ($sort === 'unread' && ! auth()->check())
                <x-call code="// as a guest: Post::whereNotViewedByVisitor($visitor->id())" />
            @endif
        </div>

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($posts as $post)
                <a href="{{ route('posts.show', $post) }}" class="panel group flex flex-col overflow-hidden hover:border-stone-300">
                    <div class="aspect-[16/9]" style="{{ $post->cover() }}"></div>
                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-xs font-medium text-accent">{{ \App\Demo\Catalog::TOPICS[$post->topic][0] }}</p>
                        <h2 class="mt-1 font-serif text-xl font-semibold leading-snug group-hover:text-accent">{{ $post->title }}</h2>
                        <p class="mt-2 line-clamp-2 text-sm text-stone-600">{{ $post->excerpt }}</p>
                        <div class="mt-auto flex items-center justify-between gap-3 pt-4 text-xs text-stone-500 tabular">
                            <span>{{ $post->author->name }} · {{ $post->published_at->format('j M Y') }}</span>
                            <span class="font-medium text-ink">
                                @switch($sort)
                                    @case('trending') score {{ Number::format($post->trending_score, maxPrecision: 1) }} @break
                                    @case('hot') hot {{ Number::format($post->hot_score, maxPrecision: 0) }} @break
                                    @case('week') {{ Number::format($post->week_views) }} this week @break
                                    @case('google') {{ Number::format($post->google_views) }} from Google @break
                                    @case('club') {{ Number::format($post->views_count) }} views @break
                                    @default {{ Number::format($post->total_views) }} views
                                @endswitch
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-stone-100 pt-2 text-[0.7rem] text-stone-500">
                            <span>{{ Number::format($weekly[$post->getKey()] ?? 0) }} in the last 7 days</span>
                            <span>{{ Number::format($post->unique_viewers) }} unique all time</span>
                        </div>
                    </div>
                </a>
            @empty
                <p class="text-stone-500">Nothing here. {{ $sort === 'unread' ? 'You have opened every article.' : '' }}</p>
            @endforelse
        </div>

        <div class="mt-8">{{ $posts->links() }}</div>

        <p class="mt-6 text-xs text-stone-500">
            The per-card “last 7 days” numbers come from one query for the page:
            <x-call class="!inline" code="Views::forViewables($posts)->period(Period::pastDays(7))->counts()" />.
            “Views” and “unique” are counter columns written by <code class="font-mono">views:recount</code>.
        </p>
    </div>
</x-layout>
