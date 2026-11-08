@props(['title' => null, 'call' => null, 'id' => null])
<section @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'panel scroll-mt-20']) }}>
    @if ($title || isset($actions))
        <header class="panel-head">
            <div class="min-w-0">
                <h2 class="panel-title">{{ $title }}</h2>
                @if ($call)
                    <x-call :code="$call" class="mt-0.5" />
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</section>
