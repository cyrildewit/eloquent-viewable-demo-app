<x-layout title="Your reading">
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <p class="eyebrow">Your reading, as a guest</p>
        <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">This browser</h1>
        <p class="mt-3 max-w-3xl text-stone-600">Without an account, your views are tied together by the <code class="font-mono text-sm">{{ config('eloquent-viewable.visitor.cookie.name') }}</code> cookie only, with the <code class="font-mono text-sm">{{ config('eloquent-viewable.visitor.identity') }}</code> identity. Sign in as one of the demo readers from the menu to see a reader's history, recommendations and privacy controls.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <x-stat label="Views from this browser" :value="$total" call="View::byVisitor($visitorId)->count()" />
            <x-stat label="Your visitor id" :value="Str::limit($visitorId, 16)" hint="The value views:forget-visitor takes." />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <x-panel title="What this browser read" call="View::byVisitor($visitorId)->with('viewable')->latest('viewed_at')->limit(12)->get()">
                <ol class="divide-y divide-stone-100">
                    @forelse ($history as $view)
                        <x-history-row :view="$view" />
                    @empty
                        <li class="py-2 text-sm text-stone-500">Nothing yet. Open a few articles.</li>
                    @endforelse
                </ol>
            </x-panel>

            <aside class="space-y-6">
                <x-panel title="Recommended for this browser" call="views(Post::class)->recommended(4)">
                    @forelse ($recommended as $item)
                        <a href="{{ route('posts.show', $item->viewable) }}" class="block py-1.5 text-sm">
                            <span class="font-medium hover:text-accent">{{ $item->viewable->title }}</span>
                            <span class="block text-xs text-stone-500">because you read “{{ $item->because->first()?->title }}”</span>
                        </a>
                    @empty
                        <p class="text-sm text-stone-500">Read a few articles first.</p>
                    @endforelse
                </x-panel>
                <x-panel title="Not opened yet" call="Post::whereNotViewedByVisitor($visitorId)">
                    @foreach ($unread as $post)
                        <a href="{{ route('posts.show', $post) }}" class="block py-1 text-sm font-medium hover:text-accent">{{ $post->title }}</a>
                    @endforeach
                </x-panel>
                <x-panel title="Forget this browser" call="php artisan views:forget-visitor {id}">
                    <form method="POST" action="{{ route('account.forget') }}">
                        @csrf
                        <button class="btn btn-sm w-full !border-rose-200 !text-rose-700 hover:!bg-rose-50">Delete every view of this browser</button>
                    </form>
                </x-panel>
            </aside>
        </div>
    </div>
</x-layout>
