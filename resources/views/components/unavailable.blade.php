@props(['reason'])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-amber-300 bg-amber-50/60 px-4 py-3 text-sm']) }}>
    <p class="font-medium text-amber-900">Not answerable for this period</p>
    <p class="mt-1 text-xs leading-relaxed text-amber-900/80">
        Part of the period was pruned, so only the rollups still hold it. The rollups keep one dimension at a time, and only those in <code class="font-mono">retention.rollups.dimensions</code>. The package refuses this read instead of counting only the views that are left.
    </p>
    <details class="mt-2 text-xs text-amber-900/70">
        <summary class="cursor-pointer">{{ class_basename($reason) }}</summary>
        <p class="mt-1 font-mono text-[0.68rem] leading-relaxed">{{ $reason->getMessage() }}</p>
    </details>
</div>
