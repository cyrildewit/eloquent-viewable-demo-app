<?php

namespace App\Http\Controllers;

use App\Console\Commands\ExamineDemo;
use App\Demo\Autopilot;
use App\Demo\Examiner;
use App\Demo\Runs;
use App\Demo\TrafficSimulator;
use App\Models\DemoEvent;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Run the package's maintenance commands from the browser and examine what
 * they changed. Only commands and options listed here can run.
 */
class ConsoleController extends Controller
{
    /**
     * Each command with the options the form offers: a flag, or a value with
     * its choices. `force` options are always passed so nothing waits for a
     * confirmation that a browser cannot give.
     */
    public const array COMMANDS = [
        'views:maintain' => [
            'about' => 'Rolls up closed buckets, anonymises, prunes and recounts counter columns, in that order. What the scheduler runs every hour.',
            'options' => ['--dry-run' => 'flag', '--max-seconds' => ['', '30', '120', '600']],
        ],
        'views:rollup' => [
            'about' => 'Folds the views of closed buckets into the hour, day and month tiers, for the built-in rollup, the newsletter rollup and the source, device and country dimensions.',
            'options' => ['--dry-run' => 'flag', '--tier' => ['', 'day', 'month', 'hour'], '--rollup' => ['', 'views', 'newsletter', 'views:source', 'views:device', 'views:country'], '--from' => 'date', '--max-seconds' => ['', '30', '120', '600']],
        ],
        'views:anonymise' => [
            'about' => 'Re-hashes the visitor under a salt per day and clears the viewer and context of views older than the retention age. Never past the last folded bucket.',
            'options' => ['--dry-run' => 'flag', '--older-than' => ['', '7d', '30d', '90d'], '--max-seconds' => ['', '30', '120', '600']],
        ],
        'views:prune' => [
            'about' => 'Deletes views older than the retention age. The rollup source keeps answering for them from the tiers.',
            'options' => ['--dry-run' => 'flag', '--older-than' => ['', '90d', '180d', '1y'], '--max-seconds' => ['', '30', '120', '600']],
        ],
        'views:recount' => [
            'about' => 'Writes the counter columns, only for posts whose counts can have changed unless --full. Checks milestones afterwards.',
            'options' => ['--full' => 'flag', '--max-seconds' => ['', '30', '120', '600']],
        ],
        'views:detect-spikes' => [
            'about' => 'Compares the last closed hour of every post with the same hour on the past 4 weeks. Fires ViewsSpiked, ViewsDropped and ViewsSettled once.',
            'options' => [],
        ],
        'views:pairs' => [
            'about' => 'Rewrites the pairs table alsoViewed() and recommended() read, from the views of the last 90 days.',
            'options' => [],
        ],
        'views:purge-bots' => [
            'about' => 'Deletes the views of visitors that opened more different models within seconds than a person could. Simulate a scraper first.',
            'options' => ['--dry-run' => 'flag', '--since' => ['1d', '7d', '30d'], '--whole-visitor' => 'flag', '--include-viewers' => 'flag'],
        ],
        'views:seed-milestones' => [
            'about' => 'Marks every post at its current count, so milestones it already passed never fire. For after an import.',
            'options' => [],
        ],
        'views:flush' => [
            'about' => 'Lands views buffered in the Redis stream. Only has work with VIEWABLE_STORE=redis.',
            'options' => ['--batch' => ['1000', '100']],
        ],
        'views:forget-visitor' => [
            'about' => 'Deletes every view of one visitor id, the value of the visitor cookie. The GDPR request of a guest.',
            'options' => ['visitor' => 'text'],
        ],
        'views:doctor' => [
            'about' => 'Checks the schema, indexes, cache stores, scheduler, proxies and config, and says what to fix.',
            'options' => ['--strict' => 'flag'],
        ],
    ];

    public function index(Request $request, Examiner $examiner): View
    {
        // The heavy part, counting the views table, comes from the snapshot
        // demo:examine takes in the background. The small tables are live.
        $snapshot = Cache::get(ExamineDemo::CacheKey);
        $active = Runs::active();

        if (is_array($snapshot)) {
            // Rows are cached as arrays; the view reads them as rows.
            $snapshot['views']['sample'] = array_map(fn (array $row): object => (object) $row, $snapshot['views']['sample'] ?? []);
            $snapshot['rollups'] = array_map(fn (array $row): object => (object) $row, $snapshot['rollups'] ?? []);
        }

        if ($snapshot === null && $active === null) {
            $active = Runs::find(Runs::start('demo:examine')['id']);
        }

        $run = $request->query('run') ? Runs::find((string) $request->query('run')) : $active;

        return view('console.index', [
            'commands' => self::COMMANDS,
            'snapshot' => $snapshot,
            'state' => $examiner->state(),
            'pairs' => $examiner->pairs(),
            'milestones' => $examiner->milestones(),
            'spikes' => $examiner->spikes(),
            'counters' => $examiner->counters(),
            'infrastructure' => $examiner->infrastructure(),
            'events' => DemoEvent::query()->latest('id')->limit(40)->get(),
            'autopilot' => Autopilot::enabled(),
            'posts' => Post::query()->published()->orderBy('title')->get(['id', 'title', 'slug', 'total_views']),
            'run' => $run,
            'runs' => Runs::recent(),
            'active' => $active,
            'examiner' => $examiner,
        ]);
    }

    /**
     * Polled by the run panel.
     */
    public function status(string $id): JsonResponse
    {
        $run = Runs::find($id) ?? abort(404);
        $snapshot = Cache::get(ExamineDemo::CacheKey);

        return response()->json([...$run, 'snapshot_at' => $snapshot['at'] ?? null]);
    }

    public function examine(): RedirectResponse
    {
        if ($active = Runs::active()) {
            return redirect()->route('console', ['run' => $active['id']])->withFragment('output');
        }

        return redirect()->route('console', ['run' => Runs::start('demo:examine')['id']])->withFragment('output');
    }

    public function run(Request $request, Examiner $examiner): RedirectResponse
    {
        $command = $request->input('command');
        abort_unless(array_key_exists($command, self::COMMANDS), 404);

        $parameters = [];

        foreach (self::COMMANDS[$command]['options'] as $option => $kind) {
            $value = $request->input('options.'.$option);

            match (true) {
                $kind === 'flag' => $value ? $parameters[$option] = true : null,
                $kind === 'date' => $value ? $parameters[$option] = date('Y-m-d', strtotime($value)) : null,
                $kind === 'text' => $parameters[$option] = (string) preg_replace('/[^A-Za-z0-9:_-]/', '', (string) $value),
                is_array($kind) && in_array($value, $kind, true) && $value !== '' => $parameters[$option] = $value,
                default => null,
            };
        }

        if (in_array($command, ['views:purge-bots', 'views:forget-visitor'], true)) {
            $parameters['--force'] = true;
        }

        $arguments = collect($parameters)
            ->map(fn ($value, $key) => $value === true ? $key : (str_starts_with($key, '--') ? "{$key}={$value}" : $value))
            ->values()
            ->all();

        if ($active = Runs::active()) {
            return redirect()->route('console', ['run' => $active['id']])->withFragment('output')
                ->with('status', 'Another command is still running. Wait for it to finish first.');
        }

        $run = Runs::start($command, $arguments);

        return redirect()->route('console', ['run' => $run['id']])->withFragment('output');
    }

    public function simulate(Request $request, TrafficSimulator $traffic): RedirectResponse
    {
        $post = $request->filled('post') ? Post::query()->findOrFail($request->integer('post')) : null;

        $status = match ($request->input('action')) {
            'recent' => 'Simulated '.number_format($traffic->recent(views: 300, minutes: 60)).' views spread over the last hour. Trending and live rankings react straight away; counter columns after <code>views:recount</code>.',
            'spike' => 'Added '.$traffic->spike($post).' Hacker News visitors to “'.e($post->title).'” in the last closed hour. Run <code>views:detect-spikes</code> to fire <code>ViewsSpiked</code>.',
            'drop' => $traffic->drop($post).' back-dated views: gave “'.e($post->title).'” about twenty views at this hour on each of the past four weeks, and none in the last closed hour. Run <code>views:detect-spikes</code> to fire <code>ViewsDropped</code>. With <code>VIEWABLE_HOUR_TIER=true</code> and those weeks already folded, run <code>views:rollup</code> first: the <code>rollup</code> source reads the hour tier, which only sees back-dated views once their buckets are folded again.',
            'milestone' => (function () use ($traffic, $post): string {
                $result = $traffic->milestone($post);

                return 'Added '.number_format($result['views']).' views to “'.e($post->title).'”, past its '.number_format($result['milestone']).' milestone. Run <code>views:recount</code> to fire <code>ViewMilestoneReached</code>.';
            })(),
            'bots' => (function () use ($traffic): string {
                $result = $traffic->bots();

                return 'A scraper, visitor <code>'.e(substr($result['visitor'], 0, 16)).'…</code>, recorded '.$result['views'].' views in three bursts over the last hour. Try <code>views:purge-bots --dry-run</code>, then with <code>--whole-visitor</code>.';
            })(),
            'live' => 'Kept '.$traffic->liveReaders(24).' made-up readers active in presence for '.config('eloquent-viewable.presence.window').' seconds. No views were recorded.',
            'reset' => null,
            default => abort(404),
        };

        if ($status === null) {
            return $this->reset($request);
        }

        return back()->with('status', $status);
    }

    /**
     * demo:reset in the background, with the size the form asks for.
     */
    private function reset(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'views' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'days' => ['required', 'integer', 'min:7', 'max:3650'],
            'focus' => ['nullable', 'in:post,episode'],
            'viral' => ['nullable', 'string', 'exists:posts,slug'],
            'viral_views' => ['nullable', 'integer', 'min:1000', 'max:50000000'],
        ]);

        if ($active = Runs::active()) {
            return redirect()->route('console', ['run' => $active['id']])->withFragment('output');
        }

        $arguments = array_values(array_filter([
            '--views='.$input['views'],
            '--days='.$input['days'],
            ! empty($input['focus']) ? '--focus='.$input['focus'] : null,
            ! empty($input['viral']) ? '--viral='.$input['viral'].':'.($input['viral_views'] ?? 1_000_000) : null,
            '--force',
        ]));

        $run = Runs::start('demo:reset', $arguments);

        return redirect()->route('console', ['run' => $run['id']])->withFragment('output');
    }

    public function autopilot(Request $request): RedirectResponse
    {
        Autopilot::set($request->boolean('enabled'));

        return back()->with('status', Autopilot::enabled()
            ? 'Autopilot on: the evd-scheduler container runs views:maintain and views:detect-spikes at the top of every hour, views:pairs at half past, and adds a trickle of readers every minute.'
            : 'Autopilot off: nothing changes the data unless you run it here.');
    }

    public function clearEvents(): RedirectResponse
    {
        DemoEvent::query()->delete();

        return back();
    }
}
