@props(['series', 'height' => 'h-44', 'tone' => 'accent'])
@php
    $values = $series->values();
    $labels = $series->labels();
    $count = max(1, count($values));
    $max = max(1, ...($values ?: [0]));
    $peak = $series->peak();
    $fill = ['accent' => 'fill-accent/75 hover:fill-accent', 'ink' => 'fill-ink/70 hover:fill-ink', 'emerald' => 'fill-emerald-500/75 hover:fill-emerald-600'][$tone];
    $ticks = collect([0, intdiv($count - 1, 2), $count - 1])->unique();
@endphp
<div {{ $attributes }}>
    <div class="relative {{ $height }}">
        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
            @foreach ([1, 0.5, 0] as $line)
                <div class="flex items-center gap-2 border-t border-dashed border-stone-200 text-[0.65rem] text-stone-400 tabular">
                    <span class="-mt-4 bg-white pr-1">{{ Number::abbreviate((int) round($max * $line), maxPrecision: 1) }}</span>
                </div>
            @endforeach
        </div>
        <svg class="absolute inset-0 size-full" viewBox="0 0 {{ $count * 10 }} 100" preserveAspectRatio="none" role="img" aria-label="Views per {{ $series->granularity->value }}">
            @foreach ($series->intervals as $i => $bucket)
                @php($h = $bucket->count / $max * 100)
                <rect x="{{ $i * 10 + 1.2 }}" y="{{ 100 - $h }}" width="7.6" height="{{ max($h, 0.4) }}" rx="1.2"
                      class="{{ $fill }} transition-colors {{ $peak && $bucket->start->eq($peak->start) ? '!fill-amber-500' : '' }}">
                    <title>{{ $bucket->label }}: {{ Number::format($bucket->count) }} views</title>
                </rect>
            @endforeach
        </svg>
    </div>
    <div class="mt-2 flex justify-between text-[0.68rem] text-stone-500 tabular">
        @foreach ($ticks as $tick)
            <span>{{ $labels[$tick] ?? '' }}</span>
        @endforeach
    </div>
</div>
