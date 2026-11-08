@props(['counts', 'labels' => [], 'empty' => 'No views with a value yet.'])
@if ($counts instanceof \Throwable)
    <x-unavailable :reason="$counts" />
@else
@php
    $values = $counts->all();
    $total = max(1, $counts->total());
    $rest = $counts->other() + $counts->none();
@endphp
<div {{ $attributes->merge(['class' => 'space-y-2.5']) }}>
    @forelse ($values as $value => $count)
        @php($share = $count / $total)
        <div>
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="truncate font-medium">{{ $labels[$value] ?? $value }}</span>
                <span class="shrink-0 text-stone-500 tabular">{{ Number::format($count) }} <span class="ml-1 inline-block w-11 text-right text-xs">{{ Number::format($share * 100, maxPrecision: 1) }}%</span></span>
            </div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-stone-100">
                <div class="h-full rounded-full bg-accent/80" style="width: {{ max(0.5, $share * 100) }}%"></div>
            </div>
        </div>
    @empty
        <p class="text-sm text-stone-500">{{ $empty }}</p>
    @endforelse
    @if ($rest > 0)
        <p class="pt-1 text-xs text-stone-500 tabular">
            @if ($counts->other() > 0) {{ Number::format($counts->other()) }} in <code class="font-mono">other()</code>@endif
            @if ($counts->other() > 0 && $counts->none() > 0) · @endif
            @if ($counts->none() > 0) {{ Number::format($counts->none()) }} without a value, <code class="font-mono">none()</code>@endif
        </p>
    @endif
</div>
@endif
