@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Inkwell</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">
    <header class="sticky top-0 z-30 border-b border-stone-200/70 bg-paper/85 backdrop-blur-md">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-5 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="font-serif text-[1.35rem] font-semibold tracking-tight">Inkwell<span class="text-accent">.</span></a>

            <nav class="hidden items-center gap-0.5 text-sm md:flex">
                @foreach ([
                    ['posts.index', 'Articles', 'posts.*'],
                    ['episodes.index', 'Podcast', 'episodes.*'],
                    ['stats', 'Stats', 'stats*'],
                    ['lab', 'Lab', 'lab*'],
                    ['console', 'Console', 'console*'],
                    ['tour', 'Tour', 'tour'],
                ] as [$route, $label, $pattern])
                    <a href="{{ route($route) }}" @class([
                        'rounded-full px-3 py-1.5 transition',
                        'bg-ink text-white' => request()->routeIs($pattern),
                        'text-stone-600 hover:bg-stone-100 hover:text-ink' => ! request()->routeIs($pattern),
                    ])>{{ $label }}</a>
                @endforeach
                <a href="/horizon" class="rounded-full px-3 py-1.5 text-stone-600 transition hover:bg-stone-100 hover:text-ink" title="Laravel Horizon: queued views and plays">Horizon ↗</a>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                @if ($navLive !== null)
                    <a href="{{ route('stats') }}#live" class="chip hidden sm:inline-flex" title="Views::live()->count(): visitors seen in the last {{ config('eloquent-viewable.presence.window') }} seconds">
                        <span class="live-dot size-1.5 rounded-full bg-emerald-500"></span>
                        <span class="tabular" data-nav-live>{{ $navLive }}</span> reading now
                    </a>
                @endif

                <details class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-stone-200 bg-white py-1 pr-3 pl-1 text-sm hover:border-stone-300">
                        @if ($me)
                            <x-avatar :user="$me" class="size-7 text-[0.65rem]" />
                            <span class="hidden font-medium sm:inline">{{ Str::before($me->name, ' ') }}</span>
                        @else
                            <span class="grid size-7 place-items-center rounded-full bg-stone-100 text-stone-500">
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 1 1 14 0H3Z"/></svg>
                            </span>
                            <span class="hidden text-stone-600 sm:inline">Guest</span>
                        @endif
                        <svg class="size-3.5 text-stone-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5.2 7.2a.75.75 0 0 1 1.06 0L10 10.94l3.74-3.74a.75.75 0 1 1 1.06 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0L5.2 8.26a.75.75 0 0 1 0-1.06Z"/></svg>
                    </summary>
                    <div class="absolute right-0 mt-2 w-72 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl shadow-stone-900/10">
                        <div class="border-b border-stone-100 px-4 py-3">
                            <p class="eyebrow">Browse as</p>
                            <p class="mt-1 text-xs text-stone-500">Signed-in views are linked to the reader through <code class="font-mono">viewer_type</code> and <code class="font-mono">viewer_id</code>.</p>
                        </div>
                        <div class="p-1.5">
                            @foreach ($people as $person)
                                <form method="POST" action="{{ route('demo.login', $person) }}">
                                    @csrf
                                    <button @class(['flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left hover:bg-stone-50', 'bg-accent-soft' => $me?->is($person)])>
                                        <x-avatar :user="$person" class="size-8 text-xs" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium">{{ $person->name }}</span>
                                            <span class="block truncate text-xs text-stone-500">{{ ucfirst($person->role) }} · {{ $person->plan }}{{ $person->tracks_reading ? '' : ' · opted out' }}</span>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                            <form method="POST" action="{{ route('demo.logout') }}">
                                @csrf
                                <button @class(['flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left text-sm hover:bg-stone-50', 'bg-accent-soft' => ! $me])>
                                    <span class="grid size-8 place-items-center rounded-full bg-stone-100 text-stone-400">?</span>
                                    Browse as a guest
                                </button>
                            </form>
                        </div>
                        <a href="{{ route('account') }}" class="block border-t border-stone-100 px-4 py-2.5 text-sm font-medium text-accent hover:bg-stone-50">Reading history and privacy →</a>
                    </div>
                </details>
            </div>
        </div>

        <nav class="flex gap-1 overflow-x-auto border-t border-stone-200/70 px-4 py-2 text-sm md:hidden">
            @foreach (['posts.index' => 'Articles', 'episodes.index' => 'Podcast', 'stats' => 'Stats', 'lab' => 'Lab', 'console' => 'Console', 'tour' => 'Tour'] as $route => $label)
                <a href="{{ route($route) }}" class="shrink-0 rounded-full px-3 py-1 text-stone-600 hover:bg-stone-100">{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    @if (session('status'))
        <div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6">
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                <div>{!! session('status') !!}</div>
            </div>
        </div>
    @endif

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t border-stone-200/80">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-stone-500 sm:px-6">
            <p>
                <span class="font-serif text-base font-semibold text-ink">Inkwell<span class="text-accent">.</span></span>
                A demo publication for <a class="font-medium text-ink underline decoration-stone-300 underline-offset-4 hover:decoration-accent" href="https://github.com/cyrildewit/eloquent-viewable/tree/9.x">cyrildewit/eloquent-viewable 9.x</a>.
            </p>
            @php($meter = app(\App\Demo\QueryMeter::class))
            <p class="font-mono text-[0.7rem] tabular" title="Every query of this request, counted by App\Demo\QueryMeter">
                {{ $meter->queries }} {{ Str::plural('query', $meter->queries) }} · {{ Number::format($meter->milliseconds, maxPrecision: 1) }} ms in the database · {{ Number::format((microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : request()->server('REQUEST_TIME_FLOAT', microtime(true)))) * 1000, maxPrecision: 0) }} ms in total{{ app()->bound(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class) && app(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)->isEnabled() ? ', with Debugbar collecting (DEBUGBAR_ENABLED=false for honest timings)' : '' }}
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($switches as $label => $value)
                    <a href="{{ route('console') }}#config" class="chip font-mono !text-[0.68rem]">{{ $label }} <span class="text-ink">{{ $value }}</span></a>
                @endforeach
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
