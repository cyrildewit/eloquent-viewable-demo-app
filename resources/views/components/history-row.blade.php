@props(['view'])
@php($model = $view->viewable)
<li class="flex items-center gap-3 py-2.5">
    @if ($model)
        <span class="size-9 shrink-0 rounded-lg" style="{{ $model->cover() }}"></span>
        <a href="{{ $model instanceof \App\Models\Post ? route('posts.show', $model) : route('episodes.show', $model) }}" class="min-w-0 flex-1 truncate text-sm font-medium hover:text-accent">{{ $model->title }}</a>
    @else
        <span class="size-9 shrink-0 rounded-lg bg-stone-100"></span>
        <span class="flex-1 text-sm text-stone-400">A deleted {{ $view->viewable_type }}</span>
    @endif
    @if ($view->collection)<span class="chip font-mono !text-[0.62rem]">{{ $view->collection }}</span>@endif
    @if ($view->source)<span class="hidden text-xs text-stone-500 sm:inline">{{ $view->source }}</span>@endif
    <span class="w-24 shrink-0 text-right text-xs text-stone-500" title="{{ $view->viewed_at }}">{{ \Carbon\Carbon::parse($view->viewed_at)->diffForHumans(short: true) }}</span>
</li>
