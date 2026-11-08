<x-layout>
    @php($lead = $hot->first())
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <div class="mb-4 flex items-end justify-between">
                    <div>
                        <p class="eyebrow">Front page</p>
                        <x-call code="Post::published()->orderByHot()->limit(7)->get()" />
                    </div>
                    <a href="{{ route('posts.index', ['sort' => 'hot']) }}" class="text-sm font-medium text-accent">All articles →</a>
                </div>

                @if ($lead)
                    <a href="{{ route('posts.show', $lead) }}" class="group panel block overflow-hidden">
                        <div class="relative aspect-[21/9]" style="{{ $lead->cover() }}">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                            <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                                <span class="chip border-white/30 bg-white/15 text-white backdrop-blur">{{ \App\Demo\Catalog::TOPICS[$lead->topic][0] }} · hot score {{ Number::format($lead->hot_score, maxPrecision: 0) }}</span>
                                <h1 class="mt-3 max-w-3xl font-serif text-3xl font-semibold leading-tight text-white sm:text-4xl">{{ $lead->title }}</h1>
                                <p class="mt-2 max-w-2xl text-white/85">{{ $lead->excerpt }}</p>
                            </div>
                        </div>
                    </a>
                @endif

                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($hot->skip(1) as $post)
                        <a href="{{ route('posts.show', $post) }}" class="group block">
                            <div class="aspect-[16/10] rounded-xl transition group-hover:opacity-90" style="{{ $post->cover() }}"></div>
                            <p class="mt-3 text-xs font-medium text-accent">{{ \App\Demo\Catalog::TOPICS[$post->topic][0] }}</p>
                            <h3 class="mt-1 font-serif text-lg font-semibold leading-snug group-hover:text-accent">{{ $post->title }}</h3>
                            <p class="mt-1 text-xs text-stone-500 tabular">{{ $post->author->name }} · {{ $post->published_at->diffForHumans() }} · {{ Number::format($post->total_views) }} views</p>
                        </a>
                    @endforeach
                </div>

                <x-panel class="mt-10" title="Recommended for {{ auth()->user()?->name ?? 'you' }}"
                         call="views(Post::class)->viewedBy($user)->period(Period::pastDays(30))->remember(10)->recommended(4)">
                    <x-slot:actions><a href="{{ route('account') }}" class="text-xs font-medium text-accent">Why these? →</a></x-slot:actions>
                    @if ($recommended->isEmpty())
                        <p class="mb-4 rounded-lg bg-stone-50 px-3 py-2 text-sm text-stone-600">
                            Nothing to recommend yet: {{ auth()->check() ? 'this reader has' : 'your browser has' }} no recent history that at least {{ config('eloquent-viewable.querying.also_viewed.minimum_visitors') }} other visitors share. Read a few posts, or sign in as Alice or Chloe from the menu. Meanwhile, what is trending:
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($recommendedFallback as $entry)
                                <a href="{{ route('posts.show', $entry->viewable) }}" class="flex gap-3">
                                    <span class="size-12 shrink-0 rounded-lg" style="{{ $entry->viewable->cover() }}"></span>
                                    <span class="text-sm font-medium leading-snug hover:text-accent">{{ $entry->viewable->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($recommended as $recommendation)
                                <a href="{{ route('posts.show', $recommendation->viewable) }}" class="group flex gap-3 rounded-xl p-2 -m-2 hover:bg-stone-50">
                                    <span class="size-14 shrink-0 rounded-lg" style="{{ $recommendation->viewable->cover() }}"></span>
                                    <span class="min-w-0">
                                        <span class="block font-medium leading-snug group-hover:text-accent">{{ $recommendation->viewable->title }}</span>
                                        <span class="mt-0.5 block text-xs text-stone-500">Because you read “{{ $recommendation->because->first()?->title }}”</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-panel>
            </div>

            <aside class="space-y-5">
                <x-panel title="Trending now" call="views(Post::class)->remember(5)->trending(5)">
                    <x-ranking :ranking="$trending" score unit="views" />
                    <p class="mt-3 text-xs text-stone-500">The score weighs each view by its age, halving every {{ config('eloquent-viewable.querying.trending.half_life') }}. <a href="{{ route('stats') }}#curves" class="text-accent">Compare curves →</a></p>
                </x-panel>

                <x-panel title="Most read this week" call="Views::period(Period::pastDays(7))->top(5)">
                    <x-ranking :ranking="$mostRead" />
                    <p class="mt-3 text-xs text-stone-500">Across every model type: articles and episodes in one list.</p>
                </x-panel>

                @if ($rising->count())
                    <x-panel title="Rising against yesterday" call="views(Post::class)->period(Period::pastDays(1))->remember(10)->rising(3)">
                        <x-ranking :ranking="$rising" score />
                    </x-panel>
                @endif

                <x-panel title="Being read right now" call="Views::live()->top(5)" id="live">
                    @if ($live === null)
                        <p class="text-sm text-rose-600">Presence needs Redis, which did not answer.</p>
                    @else
                        <x-ranking :ranking="$live" unit="reading" empty="Nobody has a page open. Open a post in another tab, or simulate readers on the Console." />
                    @endif
                </x-panel>
            </aside>
        </div>

        <section class="mt-14">
            <div class="mb-4 flex items-end justify-between">
                <div>
                    <p class="eyebrow">Inkwell Radio</p>
                    <x-call code="Views::forViewables($episodes)->collection('plays')->counts()" />
                </div>
                <a href="{{ route('episodes.index') }}" class="text-sm font-medium text-accent">All episodes →</a>
            </div>
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($episodes as $episode)
                    <a href="{{ route('episodes.show', $episode) }}" class="panel group flex gap-4 p-4 hover:border-stone-300">
                        <span class="size-20 shrink-0 rounded-xl" style="{{ $episode->cover() }}"></span>
                        <span class="min-w-0">
                            <span class="text-xs font-medium text-stone-500">Episode {{ $episode->number }} · {{ $episode->duration_minutes }} min</span>
                            <span class="mt-0.5 block font-serif text-lg font-semibold leading-snug group-hover:text-accent">{{ $episode->title }}</span>
                            <span class="mt-1 block text-xs text-stone-500 tabular">▶ {{ Number::format($plays[$episode->getKey()] ?? 0) }} plays</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layout>
