<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $post->title }} · Inkwell Lite</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="bg-paper">
    <div class="mx-auto max-w-2xl px-5 py-10">
        <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
            <p class="font-semibold">You are reading a cached page.</p>
            <p class="mt-1">This HTML was rendered {{ $renderedAt->format('H:i:s') }} and is served from the cache for 10 minutes, the same for every visitor, so the controller never records a view. The beacon below posts to a signed URL once the page has loaded, passes the same guards as <code class="font-mono">record()</code>, and keeps you counted as reading while the tab is open.</p>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-white/70 p-3 font-mono text-xs">&#64;viewsBeacon($post, collection: 'amp', live: true)</pre>
            <p class="mt-3"><span class="font-semibold tabular" data-views-live>…</span> people reading this edition, written into <code class="font-mono">[data-views-live]</code> by the beacon's script with <code class="font-mono">presence.expose_count</code> on.</p>
        </div>

        <a href="{{ route('posts.show', $post) }}" class="text-sm font-medium text-accent">← Full edition</a>
        <h1 class="mt-3 font-serif text-4xl font-semibold leading-tight">{{ $post->title }}</h1>
        <p class="mt-2 text-sm text-stone-500">{{ $post->author->name }} · {{ $post->published_at->format('j F Y') }}</p>
        <div class="prose-ink mt-8">
            @foreach (preg_split("/\n\n+/", $post->body) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </div>

    @viewsBeacon($post, collection: 'amp', live: true)
</body>
</html>
