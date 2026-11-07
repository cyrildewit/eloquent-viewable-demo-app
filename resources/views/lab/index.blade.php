<x-layout title="Recording lab">
    @php
        $old = fn (string $key, $default = null) => $input[$key] ?? $default;
        $latest = $history[0] ?? null;
        $burst = session('lab.burst');
    @endphp
    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <p class="eyebrow">Recording lab</p>
        <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">Why did that view not count?</h1>
        <p class="mt-3 max-w-3xl text-stone-600">
            Make up a visitor and send a real <code class="font-mono text-sm">attempt()</code> through the guards in <code class="font-mono text-sm">recording.guards</code>. Every guard reads the visitor through the <code class="font-mono text-sm">Visitor</code> contract, so a made-up one, passed with <code class="font-mono text-sm">useVisitor()</code>, is checked exactly like a browser. Recorded views land in the real views table.
        </p>

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <form method="POST" action="{{ route('lab.attempt') }}" class="panel p-6">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">What they open</label>
                        <select name="target" class="input">
                            @foreach ($targets as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach ($options as $value => $label)
                                        <option value="{{ $value }}" @selected($old('target') === $value)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Browser <span class="text-stone-400">→ user agent, Device dimension</span></label>
                        <select name="agent" class="input">
                            @foreach (\App\Demo\SimulatedVisitor::AGENTS as $key => [$label])
                                <option value="{{ $key }}" @selected($old('agent', 'chrome') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">IP address <span class="text-stone-400">→ IgnoreIpAddresses, bursts</span></label>
                        <select name="ip" class="input">
                            @foreach (\App\Http\Controllers\LabController::IPS as $ip => $label)
                                <option value="{{ $ip }}" @selected($old('ip') === $ip)>{{ $label }} ({{ $ip }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Arrived from <span class="text-stone-400">→ arrivedFrom(), Source, Medium</span></label>
                        <select name="arrival" class="input">
                            @foreach (\App\Http\Controllers\LabController::ARRIVALS as $key => [$label, $referrer, $landing])
                                <option value="{{ $key }}" @selected($old('arrival', 'google') === $key)>{{ $label }}{{ $landing ? ' ?'.http_build_query($landing) : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">CF-IPCountry header <span class="text-stone-400">→ Country</span></label>
                        <select name="country" class="input">
                            @foreach (\App\Http\Controllers\LabController::COUNTRIES as $code => $label)
                                <option value="{{ $code }}" @selected($old('country') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Signed in as <span class="text-stone-400">→ viewedBy()</span></label>
                        <select name="viewer" class="input">
                            <option value="">Nobody, a guest</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) $old('viewer') === (string) $user->id)>{{ $user->name }} ({{ $user->role }}{{ $user->tracks_reading ? '' : ', opted out' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Cooldown <span class="text-stone-400">→ cooldown()</span></label>
                        <select name="cooldown" class="input">
                            @foreach ([0 => 'None', 1 => '1 minute', 5 => '5 minutes', 30 => '30 minutes'] as $minutes => $label)
                                <option value="{{ $minutes }}" @selected((int) $old('cooldown', 5) === $minutes)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Collection <span class="text-stone-400">→ collection()</span></label>
                        <select name="collection" class="input">
                            <option value="">Default</option>
                            <option value="feed" @selected($old('collection') === 'feed')>feed</option>
                            <option value="amp" @selected($old('collection') === 'amp')>amp</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Context note <span class="text-stone-400">→ context(['note' => …])</span></label>
                        <input name="note" value="{{ $old('note') }}" class="input" placeholder="Anything, kept in the JSON column">
                    </div>
                </div>

                <fieldset class="mt-5 grid gap-2 sm:grid-cols-3">
                    @foreach ([
                        'dnt' => ['Sends DNT: 1', 'IgnoreDoNotTrack'],
                        'gpc' => ['Sends Sec-GPC: 1', 'IgnoreGlobalPrivacyControl'],
                        'prefetch' => ['Is a prefetch', 'IgnorePrefetch'],
                        'head' => ['Is a HEAD request', 'IgnoreHeadRequests'],
                        'queue' => ['Queue the write', 'queue()'],
                    ] as $name => [$label, $hint])
                        <label class="flex items-start gap-2.5 rounded-lg border border-stone-200 px-3 py-2 text-sm hover:bg-stone-50">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1" class="mt-0.5 accent-[var(--color-accent)]" @checked($old($name))>
                            <span>{{ $label }}<span class="block font-mono text-[0.65rem] text-stone-500">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-5">
                    <button class="btn btn-accent">Attempt a view</button>
                    <span class="text-xs text-stone-500">as visitor <code class="font-mono">{{ $visitor }}</code></span>
                </div>
                <x-call class="mt-3" code="views($model)->useVisitor($visitor)->viewedBy($viewer)->arrivedFrom($arrival)->cooldown($minutes)->attempt()" />
            </form>

            <aside class="space-y-5">
                <div class="panel p-5">
                    <p class="eyebrow">The guards, in order</p>
                    <ol class="mt-3 space-y-1.5 text-sm">
                        @foreach ($guards as $i => $guard)
                            <li @class(['flex items-center gap-2 rounded-lg px-2 py-1', 'bg-amber-50 font-semibold text-amber-900' => $latest && $latest['guard'] === class_basename($guard)])>
                                <span class="w-4 text-right text-xs text-stone-400 tabular">{{ $i + 1 }}</span>
                                <span class="font-mono text-xs">{{ class_basename($guard) }}</span>
                                @if (str_starts_with($guard, 'App\\'))<span class="chip !py-0 !text-[0.6rem]">demo</span>@endif
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-3 text-xs text-stone-500">The first that refuses drops the view. DNT and GPC are on only with <code class="font-mono">VIEWABLE_PRIVACY_SIGNALS=true</code>, because Brave sends GPC by default.</p>
                </div>

                <div class="panel p-5">
                    <p class="eyebrow">Burst guard</p>
                    <p class="mt-2 text-sm text-stone-600">One visitor opens twelve different pages in the same second. <code class="font-mono text-xs">IgnoreBursts</code> lets {{ config('eloquent-viewable.recording.bursts.max') }} through, then blocks them for {{ config('eloquent-viewable.recording.bursts.block_for') }} seconds and fires <code class="font-mono text-xs">BurstDetected</code>.</p>
                    <form method="POST" action="{{ route('lab.burst') }}" class="mt-3">@csrf<button class="btn btn-sm">Run a burst</button></form>
                </div>

                <form method="POST" action="{{ route('lab.visitor') }}" class="panel flex items-center justify-between gap-3 p-5">
                    @csrf
                    <p class="text-sm text-stone-600">Cooldowns follow the visitor id.</p>
                    <button class="btn btn-sm shrink-0">New visitor</button>
                </form>
            </aside>
        </div>

        @if ($burst)
            <x-panel class="mt-6" id="burst" title="Burst: twelve pages in one second" call="IgnoreBursts · recording.bursts">
                <ol class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($burst as $i => $row)
                        <li @class(['flex items-center gap-2 rounded-lg px-3 py-2 text-sm', 'bg-emerald-50' => $row['recorded'], 'bg-rose-50' => ! $row['recorded']])>
                            <span class="w-5 text-xs text-stone-500 tabular">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $row['title'] }}</span>
                            <span class="shrink-0 font-mono text-[0.65rem]">{{ $row['recorded'] ? 'recorded' : $row['guard'] }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-3 text-xs text-stone-500">The first views of a burst are stored. <code class="font-mono">views:purge-bots</code> on the Console deletes them afterwards.</p>
            </x-panel>
        @endif

        <x-panel class="mt-6" id="result" title="Attempts by this visitor" call="RecordResult: recorded, queued, skippedBy, present, dimensions">
            @forelse ($history as $i => $row)
                <div @class(['flex flex-col gap-2 border-b border-stone-100 py-3 last:border-0 sm:flex-row sm:items-start', 'rounded-xl bg-stone-50 px-3 -mx-3' => $i === 0])>
                    <span class="w-16 shrink-0 font-mono text-xs text-stone-500">{{ $row['at'] }}</span>
                    <span @class(['w-24 shrink-0 text-xs font-semibold', 'text-emerald-700' => $row['recorded'], 'text-amber-700' => ! $row['recorded']])>
                        {{ $row['recorded'] ? ($row['queued'] ? 'queued' : 'recorded') : 'skipped' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm"><span class="font-medium">{{ $row['title'] }}</span> <span class="text-stone-500">· {{ $row['agent'] }} · {{ $row['arrival'] }}</span></p>
                        @if ($row['guard'])
                            <p class="mt-0.5 text-sm text-amber-800"><span class="font-mono text-xs">{{ $row['guard'] }}</span>: {{ $row['why'] }} @if ($row['present']) <span class="text-stone-500">Still counted as present.</span>@endif</p>
                        @endif
                        @if ($row['dimensions'])
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach ($row['dimensions'] as $name => $value)
                                    <span class="chip font-mono !text-[0.65rem]">{{ $name }} <span @class(['text-ink' => $value !== null, 'text-stone-400' => $value === null])>{{ $value ?? 'null' }}</span></span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-500">No attempts yet. Pick a visitor above and press “Attempt a view”. Try Googlebot, the office IP, Dan (opted out), or the same post twice.</p>
            @endforelse
        </x-panel>
    </div>
</x-layout>
