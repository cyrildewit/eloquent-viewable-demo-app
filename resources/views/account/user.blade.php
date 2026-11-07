<x-layout title="Your reading">
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <div class="flex flex-wrap items-center gap-5">
            <x-avatar :user="$user" class="size-16 text-xl" />
            <div>
                <p class="eyebrow">Your reading · {{ $user->plan }} plan</p>
                <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">{{ $user->name }}</h1>
                <p class="mt-1 text-sm text-stone-500">{{ $user->bio }}</p>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <x-stat label="Articles read, last 30 days" :value="$monthly" call="views(Post::class)->viewedBy($user)->period(Period::pastDays(30))->count()" />
            <x-stat label="Different articles this week" :value="$readThisWeek" call="Post::whereViewedBy($user, Period::pastDays(7))->count()" />
            <x-stat label="Visitor id of this browser" :value="Str::limit($visitorId, 12)" hint="The cookie value. Signed-in views also carry viewer_type and viewer_id." />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                <x-panel title="Recommended for you, and why" call="$user->recommended(Post::class, limit: 6, period: Period::pastDays(90))">
                    @if ($recommended->isEmpty())
                        <p class="text-sm text-stone-500">Nothing yet. A recommendation needs {{ config('eloquent-viewable.querying.also_viewed.minimum_visitors') }} other visitors who read the same thing as you. Read a few posts and come back.</p>
                    @else
                        <ol class="divide-y divide-stone-100">
                            @foreach ($recommended as $item)
                                <li class="flex gap-4 py-3 first:pt-0 last:pb-0">
                                    <span class="w-5 pt-1 text-right font-serif text-sm font-semibold text-stone-400">{{ $item->rank }}</span>
                                    <span class="size-12 shrink-0 rounded-lg" style="{{ $item->viewable->cover() }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('posts.show', $item->viewable) }}" class="font-medium hover:text-accent">{{ $item->viewable->title }}</a>
                                        <p class="mt-0.5 text-xs text-stone-500">Because you read {{ $item->because->map(fn ($m) => '“'.$m->title.'”')->implode(', ', ' and ') }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs text-stone-500 tabular" title="Only comparable within this list">{{ Number::format($item->score, maxPrecision: 3) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    <div class="mt-4 rounded-lg bg-stone-50 px-3 py-2.5 text-xs text-stone-600">
                        The same list as a query scope, so it paginates and filters like any query:
                        <x-call class="mt-1" code="Post::published()->recommendedFor($user, period: Period::pastDays(90))->limit(3)->get()" />
                        <p class="mt-1">{{ $scoped->map(fn ($p) => $p->title.' ('.Number::format($p->recommendation_score, maxPrecision: 3).')')->implode(' · ') ?: 'Empty.' }}</p>
                    </div>
                </x-panel>

                <x-panel title="Reading history" call="$user->viewed()->with('viewable')->paginate(12)">
                    <ol class="divide-y divide-stone-100">
                        @forelse ($history as $view)
                            <x-history-row :view="$view" />
                        @empty
                            <li class="py-2 text-sm text-stone-500">No views linked to you.</li>
                        @endforelse
                    </ol>
                    <div class="mt-4">{{ $history->links() }}</div>
                </x-panel>
            </div>

            <aside class="space-y-6">
                <x-panel title="Not opened yet" call="Post::whereNotViewedBy($user)->latest()->limit(4)">
                    <div class="space-y-3">
                        @forelse ($unread as $post)
                            <a href="{{ route('posts.show', $post) }}" class="flex gap-3 text-sm">
                                <span class="size-10 shrink-0 rounded-lg" style="{{ $post->cover() }}"></span>
                                <span class="font-medium leading-snug hover:text-accent">{{ $post->title }}</span>
                            </a>
                        @empty
                            <p class="text-sm text-stone-500">You have read everything.</p>
                        @endforelse
                    </div>
                </x-panel>

                <x-panel title="Privacy" call="ViewerCanOptOut · HasViewHistory">
                    <form method="POST" action="{{ route('account.tracking') }}" class="flex items-start justify-between gap-4">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $user->tracks_reading ? 0 : 1 }}">
                        <div>
                            <p class="text-sm font-medium">Record my reading history</p>
                            <p class="text-xs text-stone-500">Off makes <code class="font-mono">tracksViews()</code> return false, and the <code class="font-mono">IgnoreOptedOutViewers</code> guard drops your views.</p>
                        </div>
                        <button @class(['relative h-6 w-11 shrink-0 rounded-full transition', 'bg-emerald-500' => $user->tracks_reading, 'bg-stone-300' => ! $user->tracks_reading])>
                            <span @class(['absolute top-0.5 size-5 rounded-full bg-white shadow transition-all', 'left-[1.375rem]' => $user->tracks_reading, 'left-0.5' => ! $user->tracks_reading])></span>
                        </button>
                    </form>

                    <div class="mt-5 space-y-3 border-t border-stone-100 pt-5">
                        <a href="{{ route('account.export') }}" class="btn btn-sm w-full">Download my views as JSON</a>
                        <x-call code="$user->exportViewHistory()->toJson()" />

                        <form method="POST" action="{{ route('account.anonymise') }}">
                            @csrf
                            <button class="btn btn-sm w-full">Anonymise my views</button>
                        </form>
                        <x-call code="$user->anonymiseViewHistory()" />

                        <form method="POST" action="{{ route('account.forget') }}" class="space-y-2">
                            @csrf
                            <label class="flex items-center gap-2 text-xs text-stone-600"><input type="checkbox" name="guests" value="1" class="accent-[var(--color-accent)]"> also the guest views of browsers I signed in on</label>
                            <button class="btn btn-sm w-full !border-rose-200 !text-rose-700 hover:!bg-rose-50">Forget my views</button>
                        </form>
                        <x-call code="$user->forgetViewHistory(includeGuestViews: true)" />
                    </div>
                    <p class="mt-4 text-xs text-stone-500">Each action dispatches an event for the audit log, visible on the <a class="text-accent" href="{{ route('console') }}#events">Console</a>. Rollups keep no visitor, so folded history stays counted.</p>
                </x-panel>
            </aside>
        </div>
    </div>
</x-layout>
