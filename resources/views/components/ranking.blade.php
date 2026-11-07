@props(['ranking', 'score' => false, 'unit' => 'views', 'empty' => 'Nothing ranks yet.'])
<ol {{ $attributes->merge(['class' => 'divide-y divide-stone-100']) }}>
    @forelse ($ranking as $entry)
        @php($model = $entry->viewable)
        <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
            <span class="w-5 shrink-0 text-right font-serif text-sm font-semibold text-stone-400 tabular">{{ $entry->rank }}</span>
            <span class="size-8 shrink-0 rounded-lg" style="{{ $model->cover() }}"></span>
            <a href="{{ $model instanceof \App\Models\Post ? route('posts.show', $model) : route('episodes.show', $model) }}" class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium hover:text-accent">{{ $model->title }}</span>
                <span class="block text-xs text-stone-500">{{ $model instanceof \App\Models\Post ? 'Article' : 'Episode '.$model->number }} · {{ \App\Demo\Catalog::TOPICS[$model->topic][0] }}</span>
            </a>
            <span class="shrink-0 text-right text-sm tabular">
                @if ($score && $entry->score !== null)
                    <span class="block font-semibold">{{ Number::format($entry->score, maxPrecision: 1) }}</span>
                    <span class="block text-xs text-stone-500">{{ Number::format($entry->count) }} {{ $unit }}</span>
                @else
                    <span class="font-semibold">{{ Number::format($entry->count) }}</span>
                    <span class="text-xs text-stone-500">{{ $unit }}</span>
                @endif
            </span>
        </li>
    @empty
        <li class="py-2 text-sm text-stone-500">{{ $empty }}</li>
    @endforelse
</ol>
