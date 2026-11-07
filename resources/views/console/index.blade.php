<x-layout title="Console">
    @php
        $toneClasses = [
            'good' => 'bg-emerald-500', 'hot' => 'bg-orange-500', 'bad' => 'bg-rose-500',
            'warn' => 'bg-amber-500', 'info' => 'bg-accent', 'muted' => 'bg-stone-300',
        ];
        $statusIcons = ['pass' => ['✓', 'text-emerald-600'], 'advice' => ['i', 'text-sky-600'], 'warning' => ['!', 'text-amber-600'], 'failure' => ['✗', 'text-rose-600'], 'skipped' => ['–', 'text-stone-400']];
        // Counting the views table is the slow part, so it comes from the
        // snapshot demo:examine takes in the background after every run.
        $counts = $snapshot['counts'] ?? [];
        $views = $snapshot['views'] ?? null;
        $timeline = collect($snapshot['timeline'] ?? []);
        $rollups = collect($snapshot['rollups'] ?? []);
        $doctor = $snapshot['doctor'] ?? null;
        $timelineMax = max([1, ...$timeline->map(fn ($m) => max($m['raw'], $m['day'], $m['month_tier'], $m['hour']))->all()]);
    @endphp

    <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="eyebrow">Console</p>
                <h1 class="mt-1 font-serif text-4xl font-semibold tracking-tight">Maintain, then examine</h1>
                <p class="mt-3 max-w-3xl text-stone-600">Queued views, plays and listeners show up in <a href="/horizon" class="text-accent">Horizon</a>. Run the commands a production app schedules, one at a time, and see what each one did to the tables. Start with a <span class="font-medium">dry run</span> of <code class="font-mono text-sm">views:maintain</code>, run it for real, then open the <a href="{{ route('stats', '2y') }}" class="text-accent">two year chart</a>: the history is still there, read from the rollups.</p>
            </div>
            <form method="POST" action="{{ route('console.autopilot') }}" class="panel flex items-center gap-4 px-4 py-3">
                @csrf
                <input type="hidden" name="enabled" value="{{ $autopilot ? 0 : 1 }}">
                <div>
                    <p class="text-sm font-semibold">Autopilot {{ $autopilot ? 'on' : 'off' }}</p>
                    <p class="text-xs text-stone-500">{{ $autopilot ? 'The scheduler maintains and adds readers.' : 'Nothing changes unless you run it.' }}</p>
                </div>
                <button @class(['relative h-6 w-11 rounded-full transition', 'bg-emerald-500' => $autopilot, 'bg-stone-300' => ! $autopilot]) aria-label="Toggle autopilot">
                    <span @class(['absolute top-0.5 size-5 rounded-full bg-white shadow transition-all', 'left-[1.375rem]' => $autopilot, 'left-0.5' => ! $autopilot])></span>
                </button>
            </form>
        </div>

        {{-- The numbers every command is compared on --}}
        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-stone-500">
                @if ($snapshot)
                    Snapshot of the tables from {{ \Carbon\Carbon::parse($snapshot['at'])->diffForHumans() }}, taken in {{ $snapshot['seconds'] }}s by <code class="font-mono">demo:examine</code> after every run.
                @else
                    Taking the first snapshot of the tables in the background…
                @endif
            </p>
            <form method="POST" action="{{ route('console.examine') }}">@csrf<button class="btn btn-sm">Refresh the snapshot</button></form>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            @forelse ($counts as $label => $value)
                <div class="panel px-4 py-3">
                    <p class="text-[0.68rem] font-medium text-stone-500">{{ $label }}</p>
                    <p class="mt-0.5 font-serif text-xl font-semibold tabular">{{ Number::format($value) }}</p>
                </div>
            @empty
                <div class="panel col-span-full px-4 py-3 text-sm text-stone-500">No snapshot yet.</div>
            @endforelse
        </div>

        @if ($run)
            <section id="output" class="mt-6 scroll-mt-20 overflow-hidden rounded-2xl bg-ink text-stone-200 shadow-xl"
                     data-run data-url="{{ route('console.runs.show', $run['id']) }}" data-id="{{ $run['id'] }}" data-status="{{ $run['status'] }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-3">
                    <p class="font-mono text-sm"><span class="text-emerald-400">$</span> php artisan {{ $run['command'] }}</p>
                    <p class="flex items-center gap-2 text-xs text-stone-400 tabular">
                        <span data-run-dot @class(['size-2 rounded-full', 'live-dot bg-amber-400' => in_array($run['status'], ['running', 'finishing']), 'bg-emerald-400' => $run['status'] === 'finished' && $run['exit'] === 0, 'bg-rose-400' => ($run['status'] === 'finished' && $run['exit'] !== 0) || $run['status'] === 'stopped'])></span>
                        <span data-run-status>{{ $run['status'] }}{{ $run['exit'] !== null ? ', exit '.$run['exit'] : '' }}</span> · <span data-run-seconds>{{ $run['seconds'] }}</span>s
                    </p>
                </div>
                <pre class="max-h-[28rem] overflow-auto px-5 py-4 font-mono text-[0.78rem] leading-relaxed" data-run-output>{{ trim($run['output']) ?: '(waiting for output)' }}</pre>
                <div class="flex flex-wrap gap-2 border-t border-white/10 px-5 py-3" data-run-diff>
                    @if ($run['after'] && $run['before'])
                        @foreach ($run['after'] as $label => $after)
                            @php($delta = $after - ($run['before'][$label] ?? 0))
                            <span @class(['rounded-full px-2.5 py-1 font-mono text-[0.7rem]', 'bg-white/5 text-stone-400' => $delta === 0, 'bg-emerald-500/20 text-emerald-300' => $delta > 0, 'bg-rose-500/20 text-rose-300' => $delta < 0])>
                                {{ $label }} {{ $delta === 0 ? '±0' : ($delta > 0 ? '+' : '').Number::format($delta) }}
                            </span>
                        @endforeach
                    @else
                        <span class="text-xs text-stone-400">The table counts before and after appear here once the command has finished.</span>
                    @endif
                </div>
            </section>
        @endif

        @if (count($runs) > 0)
            <div class="mt-4 flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-stone-500">Recent runs:</span>
                @foreach ($runs as $recent)
                    <a href="{{ route('console', ['run' => $recent['id']]) }}#output" @class([
                        'chip font-mono !text-[0.65rem] hover:border-stone-300',
                        '!border-ink' => $run && $run['id'] === $recent['id'],
                    ])>
                        <span @class(['size-1.5 rounded-full', 'bg-amber-400' => in_array($recent['status'], ['running', 'finishing']), 'bg-emerald-500' => $recent['status'] === 'finished' && $recent['exit'] === 0, 'bg-rose-500' => ($recent['status'] === 'finished' && $recent['exit'] !== 0) || $recent['status'] === 'stopped'])></span>
                        {{ Str::limit($recent['command'], 42) }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                {{-- Commands --}}
                <section id="commands" class="scroll-mt-20">
                    <h2 class="font-serif text-2xl font-semibold">Run a command</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ($commands as $command => $definition)
                            <form method="POST" action="{{ route('console.run') }}" class="panel flex flex-col p-5" onsubmit="const b = this.querySelector('button'); b.disabled = true; b.textContent = 'Running…'">
                                @csrf
                                <input type="hidden" name="command" value="{{ $command }}">
                                <p class="font-mono text-sm font-semibold">{{ $command }}</p>
                                <p class="mt-1.5 flex-1 text-sm text-stone-600">{{ $definition['about'] }}</p>
                                @if ($definition['options'])
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        @foreach ($definition['options'] as $option => $kind)
                                            @if ($kind === 'flag')
                                                <label class="chip cursor-pointer font-mono hover:border-stone-300">
                                                    <input type="checkbox" name="options[{{ $option }}]" value="1" class="accent-[var(--color-accent)]" @checked($option === '--dry-run')> {{ $option }}
                                                </label>
                                            @elseif ($kind === 'date')
                                                <label class="chip font-mono">{{ $option }} <input type="date" name="options[{{ $option }}]" class="w-32 bg-transparent text-xs outline-none"></label>
                                            @elseif ($kind === 'text')
                                                <input type="text" name="options[{{ $option }}]" placeholder="{{ $option }} id" class="input !w-full font-mono !text-xs" required>
                                            @else
                                                <label class="chip font-mono">{{ $option }}
                                                    <select name="options[{{ $option }}]" class="bg-transparent text-xs outline-none">
                                                        @foreach ($kind as $choice)
                                                            <option value="{{ $choice }}">{{ $choice === '' ? 'config' : $choice }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                <button class="btn btn-primary btn-sm mt-4 self-start">Run</button>
                            </form>
                        @endforeach
                    </div>
                </section>

                {{-- Where history lives --}}
                @if ($views)
                <x-panel id="timeline" title="Where each month of history lives" call="views table vs. view_rollups, rollup 'views', grouping 'type'">
                    <div class="overflow-x-auto">
                        <div class="flex h-48 min-w-[40rem] items-end gap-1">
                            @foreach ($timeline as $month)
                                <div class="group flex h-full flex-1 flex-col justify-end" title="{{ $month['month'] }}: {{ number_format($month['raw']) }} rows in views · day tier {{ number_format($month['day']) }} · month tier {{ number_format($month['month_tier']) }} · hour tier {{ number_format($month['hour']) }}">
                                    <div class="flex h-full items-end gap-px">
                                        <div class="flex-1 rounded-t-sm bg-accent/80" style="height: {{ $month['raw'] / $timelineMax * 100 }}%"></div>
                                        <div class="flex-1 rounded-t-sm bg-emerald-500/70" style="height: {{ $month['day'] / $timelineMax * 100 }}%"></div>
                                        <div class="flex-1 rounded-t-sm bg-amber-500/80" style="height: {{ $month['month_tier'] / $timelineMax * 100 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex min-w-[40rem] gap-1 text-[0.6rem] text-stone-400">
                            @foreach ($timeline as $i => $month)
                                <span class="flex-1 text-center">{{ $i % 3 === 0 ? \Carbon\Carbon::parse($month['month'].'-01')->format('M y') : '' }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-xs text-stone-600">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-accent/80"></span>rows in the views table</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-500/70"></span>day tier, kept {{ config('eloquent-viewable.retention.rollups.tiers.day') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber-500/80"></span>month tier, kept forever</span>
                    </div>
                    <p class="mt-3 text-sm text-stone-600">Before maintenance only the purple bars exist. <code class="font-mono text-xs">views:rollup</code> adds the green and amber ones, and <code class="font-mono text-xs">views:prune</code> then removes the purple bars older than {{ $views['prune_after'] }}. The <code class="font-mono text-xs">rollup</code> source adds the parts up, so counts stay the same.</p>
                </x-panel>

                {{-- Views table --}}
                <x-panel title="The views table" call="select * from views order by id desc limit 6">
                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-stone-500">oldest view</dt><dd class="font-medium tabular">{{ $views['oldest'] ? \Carbon\Carbon::parse($views['oldest'])->format('j M Y') : '–' }}</dd></div>
                        <div><dt class="text-xs text-stone-500">newest view</dt><dd class="font-medium tabular">{{ $views['newest'] ? \Carbon\Carbon::parse($views['newest'])->diffForHumans() : '–' }}</dd></div>
                        <div><dt class="text-xs text-stone-500">waiting to be anonymised ({{ $views['anonymise_after'] }})</dt><dd class="font-medium tabular">{{ Number::format($views['to_anonymise'] ?? 0) }}</dd></div>
                        <div><dt class="text-xs text-stone-500">past the prune age ({{ $views['prune_after'] }})</dt><dd class="font-medium tabular">{{ Number::format($views['to_prune'] ?? 0) }}</dd></div>
                        <div><dt class="text-xs text-stone-500">anonymised (<code class="font-mono">a:</code> visitor)</dt><dd class="font-medium tabular">{{ Number::format($views['anonymised']) }}</dd></div>
                        <div><dt class="text-xs text-stone-500">linked to a viewer</dt><dd class="font-medium tabular">{{ Number::format($views['with_viewer']) }}</dd></div>
                        <div><dt class="text-xs text-stone-500">with context</dt><dd class="font-medium tabular">{{ Number::format($views['with_context']) }}</dd></div>
                        <div><dt class="text-xs text-stone-500">total rows</dt><dd class="font-medium tabular">{{ Number::format($views['total']) }}</dd></div>
                    </dl>
                    <div class="mt-5 overflow-x-auto rounded-lg border border-stone-100">
                        <table class="w-full min-w-[52rem] text-left font-mono text-[0.68rem]">
                            <thead class="bg-stone-50 text-stone-500">
                                <tr>@foreach (['id', 'viewable', 'viewer', 'visitor', 'collection', 'context', 'viewed_at', 'source', 'medium', 'device', 'country'] as $column)<th class="px-2 py-1.5 font-medium">{{ $column }}</th>@endforeach</tr>
                            </thead>
                            <tbody>
                                @foreach ($views['sample'] as $row)
                                    <tr class="border-t border-stone-100">
                                        <td class="px-2 py-1.5">{{ $row->id }}</td>
                                        <td class="px-2 py-1.5">{{ $row->viewable_type }}:{{ $row->viewable_id }}</td>
                                        <td class="px-2 py-1.5">{{ $row->viewer_type ? $row->viewer_type.':'.$row->viewer_id : 'null' }}</td>
                                        <td class="max-w-[8rem] truncate px-2 py-1.5">{{ $row->visitor }}</td>
                                        <td class="px-2 py-1.5">{{ $row->collection ?? 'null' }}</td>
                                        <td class="max-w-[10rem] truncate px-2 py-1.5">{{ $row->context ?? 'null' }}</td>
                                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $row->viewed_at }}</td>
                                        <td class="px-2 py-1.5">{{ $row->source ?? 'null' }}</td>
                                        <td class="px-2 py-1.5">{{ $row->medium ?? 'null' }}</td>
                                        <td class="px-2 py-1.5">{{ $row->device ?? 'null' }}</td>
                                        <td class="px-2 py-1.5">{{ $row->country ?? 'null' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-panel>
                @endif

                {{-- Rollups --}}
                <x-panel id="rollups" title="The rollups table" call="select rollup, tier, grouping, count(*) … from view_rollups group by rollup, tier, grouping">
                    @if ($rollups->isEmpty())
                        <p class="text-sm text-stone-500">Empty. Nothing has been folded yet: run <code class="font-mono">views:rollup --dry-run</code> to see how many buckets wait, then without it.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[40rem] text-left text-sm">
                                <thead class="text-xs text-stone-500"><tr><th class="pb-2 font-medium">rollup</th><th class="pb-2 font-medium">tier</th><th class="pb-2 font-medium">grouping</th><th class="pb-2 text-right font-medium">rows</th><th class="pb-2 text-right font-medium">buckets</th><th class="pb-2 font-medium">from – to</th><th class="pb-2 text-right font-medium">views</th></tr></thead>
                                <tbody class="tabular">
                                    @foreach ($rollups as $row)
                                        <tr class="border-t border-stone-100">
                                            <td class="py-1.5 font-mono text-xs">{{ $row->rollup }}</td>
                                            <td class="py-1.5"><span class="chip !py-0">{{ $row->tier }}</span></td>
                                            <td class="py-1.5 font-mono text-xs">{{ $row->grouping }}</td>
                                            <td class="py-1.5 text-right">{{ Number::format($row->rows) }}</td>
                                            <td class="py-1.5 text-right">{{ Number::format($row->buckets) }}</td>
                                            <td class="py-1.5 text-xs text-stone-500">{{ \Carbon\Carbon::parse($row->first_bucket)->format('j M Y') }} – {{ \Carbon\Carbon::parse($row->last_bucket)->format('j M Y H:i') }}</td>
                                            <td class="py-1.5 text-right">{{ Number::format($row->views) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <p class="mt-3 text-xs text-stone-500">Tiers: {{ collect(config('eloquent-viewable.retention.rollups.tiers'))->map(fn ($age, $tier) => $tier.' kept '.($age ?? 'forever'))->implode(', ') }}. A bucket waits {{ config('eloquent-viewable.retention.rollups.settle') }} after it closes before it is folded.</p>
                </x-panel>

                <div class="grid gap-6 md:grid-cols-2">
                    <x-panel title="Watermarks" call="select * from view_state">
                        @forelse ($state as $row)
                            <div class="border-b border-stone-100 py-1.5 last:border-0">
                                <p class="font-mono text-[0.7rem] font-medium">{{ $row->name }}</p>
                                <p class="truncate font-mono text-[0.68rem] text-stone-500" title="{{ $row->value }}">{{ $row->value }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-stone-500">No state yet. The commands keep how far they got here, so a run that stops carries on next time.</p>
                        @endforelse
                    </x-panel>

                    <x-panel title="Counter columns" call="views:recount → posts.total_views, unique_viewers, …">
                        <table class="w-full text-xs tabular">
                            <thead class="text-left text-stone-500"><tr><th class="pb-1.5 font-medium">post</th><th class="pb-1.5 text-right font-medium">total</th><th class="pb-1.5 text-right font-medium">unique</th><th class="pb-1.5 text-right font-medium">7d</th><th class="pb-1.5 text-right font-medium">hot</th></tr></thead>
                            <tbody>
                                @foreach ($counters as $post)
                                    <tr class="border-t border-stone-100"><td class="max-w-[9rem] truncate py-1.5"><a class="hover:text-accent" href="{{ route('posts.stats', $post) }}">{{ $post->title }}</a></td><td class="py-1.5 text-right">{{ Number::format($post->total_views) }}</td><td class="py-1.5 text-right">{{ Number::format($post->unique_viewers) }}</td><td class="py-1.5 text-right">{{ Number::format($post->views_last_week) }}</td><td class="py-1.5 text-right">{{ Number::format($post->hot_score, maxPrecision: 0) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-panel>

                    <x-panel title="Milestone marks" call="select * from view_milestones">
                        @forelse ($milestones as $row)
                            <p class="flex justify-between gap-3 border-b border-stone-100 py-1.5 text-xs last:border-0"><span class="truncate">{{ $examiner->title($row->viewable_type, $row->viewable_id) }}</span><span class="shrink-0 font-mono">{{ $row->column }} @ {{ Number::format($row->high_water) }}</span></p>
                        @empty
                            <p class="text-sm text-stone-500">No marks. The first recount marks every post at its current count.</p>
                        @endforelse
                    </x-panel>

                    <x-panel title="Spikes and pairs" call="view_spikes · view_pairs">
                        @forelse ($spikes as $row)
                            <p class="text-xs"><span @class(['font-semibold', 'text-emerald-700' => $row->direction === 'spike', 'text-rose-700' => $row->direction === 'drop'])>{{ $row->direction }}</span> {{ $examiner->title($row->viewable_type, $row->viewable_id) }}, peak {{ $row->peak_count }}{{ $row->quiet_since ? ', quiet' : '' }}</p>
                        @empty
                            <p class="text-sm text-stone-500">Nothing spiking.</p>
                        @endforelse
                        <div class="mt-3 border-t border-stone-100 pt-3">
                            <p class="text-xs text-stone-500">{{ Number::format($pairs['rows']) }} pairs. Strongest:</p>
                            @foreach ($pairs['strongest'] as $pair)
                                <p class="mt-1 truncate text-xs">{{ $examiner->title($pair->viewable_type, $pair->viewable_id) }} <span class="text-stone-400">+</span> {{ $examiner->title($pair->paired_type, $pair->paired_id) }} <span class="text-stone-500 tabular">({{ $pair->visitors }})</span></p>
                            @endforeach
                        </div>
                    </x-panel>
                </div>
            </div>

            <aside class="space-y-6">
                {{-- Simulate --}}
                <x-panel id="simulate" title="Simulate" call="App\Demo\TrafficSimulator">
                    <form method="POST" action="{{ route('console.simulate') }}" class="space-y-3">
                        @csrf
                        <select name="post" class="input">
                            @foreach ($posts as $post)
                                <option value="{{ $post->id }}">{{ $post->title }} ({{ Number::abbreviate($post->total_views) }})</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <button name="action" value="spike" class="btn btn-sm">A spike</button>
                            <button name="action" value="drop" class="btn btn-sm">A drop</button>
                            <button name="action" value="milestone" class="btn btn-sm col-span-2">Carry it past a milestone</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('console.simulate') }}" class="mt-3 grid grid-cols-2 gap-2 border-t border-stone-100 pt-3">
                        @csrf
                        <button name="action" value="recent" class="btn btn-sm">An hour of traffic</button>
                        <button name="action" value="live" class="btn btn-sm">24 live readers</button>
                        <button name="action" value="bots" class="btn btn-sm col-span-2">A scraper in three bursts</button>
                    </form>
                </x-panel>

                {{-- Reset --}}
                <x-panel id="reset" title="Rebuild with more history" call="php artisan demo:reset --views=… --days=…">
                    <form method="POST" action="{{ route('console.simulate') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="action" value="reset">
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block"><span class="label">Views</span>
                                <select name="views" class="input">
                                    @foreach ([110_000 => '110,000', 1_000_000 => '1 million', 5_000_000 => '5 million', 10_000_000 => '10 million', 20_000_000 => '20 million'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block"><span class="label">Over</span>
                                <select name="days" class="input">
                                    <option value="730">2 years</option>
                                    <option value="1095">3 years</option>
                                    <option value="365">1 year</option>
                                    <option value="90">90 days</option>
                                </select>
                            </label>
                        </div>
                        <label class="block"><span class="label">Mostly on</span>
                            <select name="focus" class="input">
                                <option value="">Articles and episodes, as usual</option>
                                <option value="post">Articles</option>
                                <option value="episode">Episodes</option>
                            </select>
                        </label>
                        <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-2">
                            <label class="block"><span class="label">A post that goes viral</span>
                                <select name="viral" class="input">
                                    <option value="">None</option>
                                    @foreach ($posts as $post)
                                        <option value="{{ $post->slug }}">{{ $post->title }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block"><span class="label">with</span>
                                <select name="viral_views" class="input">
                                    <option value="100000">100,000</option>
                                    <option value="1000000" selected>1 million</option>
                                    <option value="5000000">5 million</option>
                                </select>
                            </label>
                        </div>
                        <button class="btn btn-sm w-full !border-rose-200 !text-rose-700 hover:!bg-rose-50">Wipe and rebuild in the background</button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">From a million views the indexes are set aside during the load and built afterwards. Expect about 450 bytes a view on disk, and roughly a minute per million on a laptop. Any size works from the command line: <code class="font-mono">php artisan demo:reset --views=20000000</code>.</p>
                </x-panel>

                {{-- Events --}}
                <x-panel id="events" title="What the package dispatched" call="App\Listeners\LogViewableEvents">
                    <x-slot:actions>
                        <form method="POST" action="{{ route('console.events.clear') }}">@csrf<button class="text-xs text-stone-500 hover:text-ink">Clear</button></form>
                    </x-slot:actions>
                    <ol class="-my-1 max-h-[34rem] space-y-0.5 overflow-y-auto">
                        @forelse ($events as $event)
                            <li class="flex gap-2.5 py-1.5">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $toneClasses[$event->tone] ?? 'bg-stone-300' }}"></span>
                                <div class="min-w-0">
                                    <p class="text-sm leading-snug">{{ $event->title }}</p>
                                    <p class="text-[0.68rem] text-stone-500"><span class="font-mono">{{ $event->event }}</span> · {{ $event->created_at->diffForHumans() }}</p>
                                    @if ($event->payload && in_array($event->event, ['ViewsSpiked', 'ViewsDropped', 'ViewMilestoneReached', 'ViewsSettled'], true))
                                        <pre class="mt-1 overflow-x-auto rounded bg-stone-50 px-2 py-1 font-mono text-[0.62rem] text-stone-600">{{ json_encode($event->payload, JSON_UNESCAPED_SLASHES) }}</pre>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-stone-500">No events yet. Run a command.</li>
                        @endforelse
                    </ol>
                </x-panel>

                {{-- Doctor --}}
                <x-panel id="doctor" title="views:doctor" call="php artisan views:doctor --json">
                    @if ($doctor)
                        <div class="space-y-3">
                            @foreach ($doctor['checks'] as $check)
                                <div>
                                    <p class="text-xs font-semibold">{{ $check['name'] }}</p>
                                    @foreach ($check['findings'] as $finding)
                                        @php([$icon, $color] = $statusIcons[$finding['status']] ?? ['·', 'text-stone-400'])
                                        <p class="mt-0.5 flex gap-1.5 text-xs leading-snug text-stone-600"><span class="w-3 shrink-0 font-bold {{ $color }}">{{ $icon }}</span><span>{{ $finding['summary'] }}@if ($finding['fix'])<span class="block text-stone-400">→ {{ $finding['fix'] }}</span>@endif</span></p>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-rose-600">The doctor could not run.</p>
                    @endif
                </x-panel>

                {{-- Config --}}
                <x-panel id="config" title="The switches" call="config/eloquent-viewable.php">
                    <dl class="space-y-2 text-xs">
                        @foreach ([
                            'VIEWABLE_IDENTITY' => config('eloquent-viewable.visitor.identity'),
                            'VIEWABLE_STORE' => config('eloquent-viewable.recording.store.driver'),
                            'VIEWABLE_QUEUE' => config('eloquent-viewable.recording.queue.enabled') ? 'true' : 'false',
                            'VIEWABLE_SOURCE' => config('eloquent-viewable.querying.source.driver'),
                            'VIEWABLE_PAIRS' => config('eloquent-viewable.querying.pairs.enabled') ? 'true' : 'false',
                            'VIEWABLE_PRIVACY_SIGNALS' => in_array(\CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack::class, config('eloquent-viewable.recording.guards'), true) ? 'true' : 'false',
                        ] as $env => $value)
                            <div class="flex justify-between gap-3"><dt class="font-mono text-stone-500">{{ $env }}</dt><dd class="font-mono font-semibold">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-stone-500">Set them in <code class="font-mono">.env</code>, then <code class="font-mono">docker compose restart php horizon scheduler</code>. Redis in Docker: {{ $infrastructure['redis'] }}. Queue: {{ $infrastructure['queue'] ?? '?' }} waiting, see <a class="text-accent" href="/horizon">Horizon</a>. Redis stream: {{ $infrastructure['stream'] ?? 0 }} buffered views.</p>
                </x-panel>
            </aside>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const panel = document.querySelector('[data-run]');

            const pill = (label, delta) => {
                const tone = delta === 0 ? 'bg-white/5 text-stone-400' : (delta > 0 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300');
                const text = delta === 0 ? '±0' : (delta > 0 ? '+' : '') + delta.toLocaleString('en');
                return `<span class="rounded-full px-2.5 py-1 font-mono text-[0.7rem] ${tone}">${label} ${text}</span>`;
            };

            async function poll() {
                const run = await (await fetch(panel.dataset.url, { headers: { Accept: 'application/json' } })).json();
                const output = panel.querySelector('[data-run-output]');
                const atBottom = output.scrollHeight - output.scrollTop - output.clientHeight < 40;

                output.textContent = run.output.trim() || '(waiting for output)';
                if (atBottom) output.scrollTop = output.scrollHeight;

                panel.querySelector('[data-run-status]').textContent = run.status + (run.exit !== null ? `, exit ${run.exit}` : '');
                panel.querySelector('[data-run-seconds]').textContent = run.seconds;

                if (run.before && run.after) {
                    panel.querySelector('[data-run-diff]').innerHTML = Object.entries(run.after)
                        .map(([label, after]) => pill(label, after - (run.before[label] ?? 0))).join('');
                }

                if (run.status === 'running' || run.status === 'finishing') {
                    setTimeout(poll, 1000);
                    return;
                }

                // The snapshot is taken after the command: reload once to show it.
                const key = `console-reloaded-${panel.dataset.id}`;
                if (! sessionStorage.getItem(key)) {
                    sessionStorage.setItem(key, '1');
                    location.reload();
                }
            }

            if (panel && ['running', 'finishing'].includes(panel.dataset.status)) {
                const output = panel.querySelector('[data-run-output]');
                output.scrollTop = output.scrollHeight;
                poll();
            }
        </script>
    @endpush
</x-layout>
