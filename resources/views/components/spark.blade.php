@props(['values', 'class' => 'h-8 w-28'])
@php
    $values = array_values($values);
    $n = max(2, count($values));
    $max = max(1, ...($values ?: [0]));
    $points = collect($values)->map(fn ($v, $i) => round($i / ($n - 1) * 100, 2).','.round(30 - $v / $max * 28, 2))->implode(' ');
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
    <polyline points="0,32 {{ $points }} 100,32" class="fill-accent/10 stroke-none" />
    <polyline points="{{ $points }}" fill="none" class="stroke-accent" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
</svg>
