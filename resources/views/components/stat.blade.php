@props(['label', 'value', 'comparison' => null, 'hint' => null, 'call' => null])
<div {{ $attributes->merge(['class' => 'panel p-5']) }}>
    <p class="eyebrow">{{ $label }}</p>
    <p class="mt-2 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-semibold tracking-tight tabular">{{ is_numeric($value) ? Number::format($value) : $value }}</span>
        @if ($comparison)
            @php($up = $comparison->delta >= 0)
            <span @class(['rounded-full px-1.5 py-0.5 text-xs font-semibold tabular', 'bg-emerald-50 text-emerald-700' => $up, 'bg-rose-50 text-rose-700' => ! $up])>
                {{ $up ? '▲' : '▼' }} {{ $comparison->percent === null ? 'new' : Number::format(abs($comparison->percent), maxPrecision: 1).'%' }}
            </span>
        @endif
    </p>
    @if ($comparison)
        <p class="mt-1 text-xs text-stone-500 tabular">{{ Number::format($comparison->previous) }} in the {{ $comparison->previousPeriod->getStartDateTime()?->diffInDays($comparison->previousPeriod->getEndDateTime()) == 1 ? 'day' : 'period' }} before ({{ $comparison->previousPeriod->getStartDateTime()?->format('j M') }} – {{ $comparison->previousPeriod->getEndDateTime()?->copy()->subSecond()->format('j M') }})</p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>
    @endif
    @if ($call)
        <x-call :code="$call" class="mt-3 border-t border-stone-100 pt-2" />
    @endif
</div>
