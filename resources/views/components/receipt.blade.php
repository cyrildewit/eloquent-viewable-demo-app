@props(['result', 'call' => 'views($post)->cooldown(5)->attempt()'])
@php($skipped = $result->skippedBy !== null)
<div {{ $attributes->merge(['class' => 'panel overflow-hidden']) }}>
    <div @class(['flex items-center gap-3 px-5 py-4', 'bg-emerald-50' => ! $skipped, 'bg-amber-50' => $skipped])>
        <span @class(['grid size-9 shrink-0 place-items-center rounded-full text-white', 'bg-emerald-500' => ! $skipped, 'bg-amber-500' => $skipped])>
            @if ($skipped)
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 0 1 1 1v6a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 11a1.25 1.25 0 1 1 0 2.5A1.25 1.25 0 0 1 10 14Z"/></svg>
            @else
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
            @endif
        </span>
        <div class="min-w-0">
            <p class="text-sm font-semibold">
                @if ($skipped)
                    Skipped by {{ \App\Demo\Guards::name($result->skippedBy) }}
                @elseif ($result->queued)
                    Queued for the worker
                @else
                    View recorded
                @endif
            </p>
            <p class="text-xs text-stone-600">
                @if ($skipped)
                    {{ \App\Demo\Guards::explain($result->skippedBy) }}
                @elseif ($result->queued)
                    Guards passed during the request; the evd-horizon container writes the row.
                @else
                    Every guard passed and the row was written.
                @endif
            </p>
        </div>
    </div>
    <dl class="grid grid-cols-2 gap-px bg-stone-100 text-xs">
        <div class="bg-white px-4 py-2.5"><dt class="text-stone-500">recorded</dt><dd class="font-mono font-medium">{{ var_export($result->recorded, true) }}</dd></div>
        <div class="bg-white px-4 py-2.5"><dt class="text-stone-500">queued</dt><dd class="font-mono font-medium">{{ var_export($result->queued, true) }}</dd></div>
        <div class="bg-white px-4 py-2.5"><dt class="text-stone-500">present</dt><dd class="font-mono font-medium" title="Kept active in presence, even when a cooldown skipped the view">{{ var_export($result->present, true) }}</dd></div>
        <div class="bg-white px-4 py-2.5"><dt class="text-stone-500">skippedBy</dt><dd class="truncate font-mono font-medium">{{ \App\Demo\Guards::name($result->skippedBy) ?? 'null' }}</dd></div>
    </dl>
    @if ($result->dimensions)
        <div class="border-t border-stone-100 px-4 py-3">
            <p class="mb-1.5 text-xs text-stone-500">Dimensions resolved from this request</p>
            <div class="flex flex-wrap gap-1">
                @foreach ($result->dimensions as $name => $value)
                    <span class="chip font-mono !text-[0.68rem]">{{ $name }} <span @class(['text-ink' => $value !== null, 'text-stone-400' => $value === null])>{{ $value ?? 'null' }}</span></span>
                @endforeach
            </div>
        </div>
    @endif
    <div class="border-t border-stone-100 px-4 py-2.5">
        <x-call :code="$call" />
    </div>
</div>
