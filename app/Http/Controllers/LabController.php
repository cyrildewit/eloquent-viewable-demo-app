<?php

namespace App\Http\Controllers;

use App\Demo\Guards;
use App\Demo\SimulatedVisitor;
use App\Models\Episode;
use App\Models\Post;
use App\Models\User;
use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Runs real attempts through the configured guards with a made-up visitor,
 * so every way a view can be refused is one click away.
 */
class LabController extends Controller
{
    public const array ARRIVALS = [
        'none' => ['Typed the address', null, []],
        'google' => ['Google search', 'https://www.google.com/', []],
        'hn' => ['Hacker News', 'https://news.ycombinator.com/item?id=1', []],
        'chatgpt' => ['ChatGPT answer', 'https://chatgpt.com/', []],
        'reddit' => ['Reddit thread', 'https://www.reddit.com/r/laravel/', []],
        'newsletter' => ['Newsletter link', null, ['utm_source' => 'nl', 'utm_medium' => 'email', 'utm_campaign' => 'Weekly-Digest']],
        'ad' => ['Paid ad', 'https://www.google.com/', ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'spring-sale']],
        'internal' => ['Another Inkwell page', 'https://eloquent-viewable-demo.localhost/posts', []],
        'unknown' => ['A blog we do not know', 'https://blog.example.org/links', []],
    ];

    public const array COUNTRIES = ['' => 'No header', 'NL' => 'Netherlands', 'US' => 'United States', 'DE' => 'Germany', 'JP' => 'Japan', 'BR' => 'Brazil'];

    public const array IPS = [
        '203.0.113.10' => 'A home network',
        '198.51.100.7' => 'The office, in ignored_ip_addresses',
        '2001:db8::1' => 'An IPv6 address',
    ];

    public function index(Request $request): View
    {
        return view('lab.index', [
            'targets' => $this->targets(),
            'users' => User::query()->orderBy('id')->limit(5)->get(),
            'visitor' => $this->visitorId($request),
            'history' => $request->session()->get('lab.history', []),
            'input' => $request->session()->get('lab.input', []),
            'guards' => config('eloquent-viewable.recording.guards'),
        ]);
    }

    public function attempt(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'target' => ['required', 'string'],
            'agent' => ['required', 'in:'.implode(',', array_keys(SimulatedVisitor::AGENTS))],
            'ip' => ['required', 'in:'.implode(',', array_keys(self::IPS))],
            'arrival' => ['required', 'in:'.implode(',', array_keys(self::ARRIVALS))],
            'country' => ['nullable', 'in:'.implode(',', array_keys(self::COUNTRIES))],
            'viewer' => ['nullable', 'integer'],
            'cooldown' => ['required', 'integer', 'min:0', 'max:60'],
            'collection' => ['nullable', 'in:feed,amp'],
            'dnt' => ['boolean'],
            'gpc' => ['boolean'],
            'prefetch' => ['boolean'],
            'head' => ['boolean'],
            'queue' => ['boolean'],
            'note' => ['nullable', 'string', 'max:60'],
        ]);

        $model = $this->resolve($input['target']);
        $viewer = isset($input['viewer']) ? User::query()->find($input['viewer']) : null;

        $result = $this->run($request, $model, $input, $viewer, $this->visitorId($request));

        $request->session()->put('lab.input', $input);
        $this->remember($request, $model, $input, $result);

        return redirect()->route('lab')->withFragment('result');
    }

    /**
     * Opens twelve different pages within the same second as one visitor. The
     * burst guard lets the first ones through and blocks the rest.
     */
    public function burst(Request $request): RedirectResponse
    {
        $visitor = 'lab-burst-'.Str::random(12);
        $ip = '203.0.113.'.random_int(20, 250);

        $targets = collect($this->targets())->flatMap(fn (array $group) => array_keys($group))->shuffle()->take(12);
        $input = ['agent' => 'chrome', 'ip' => $ip, 'arrival' => 'none', 'cooldown' => 0, 'country' => null, 'collection' => null];

        $results = [];

        foreach ($targets as $target) {
            $model = $this->resolve($target);
            $result = $this->run($request, $model, $input, null, $visitor);
            $results[] = ['title' => $model->title, 'recorded' => $result->recorded, 'guard' => Guards::name($result->skippedBy)];
        }

        $request->session()->flash('lab.burst', $results);

        return redirect()->route('lab')->withFragment('burst');
    }

    public function newVisitor(Request $request): RedirectResponse
    {
        $request->session()->put('lab.visitor', 'lab-'.Str::random(16));
        $request->session()->forget('lab.history');

        return redirect()->route('lab')->with('status', 'A new visitor: no cooldowns, no burst history.');
    }

    private function run(Request $request, Post|Episode $model, array $input, ?User $viewer, string $visitorId): RecordResult
    {
        $visitor = new SimulatedVisitor(
            id: $visitorId,
            userAgent: SimulatedVisitor::agent($input['agent']),
            ip: $input['ip'],
            viewer: $viewer,
            doNotTrack: (bool) ($input['dnt'] ?? false),
            globalPrivacyControl: (bool) ($input['gpc'] ?? false),
            prefetch: (bool) ($input['prefetch'] ?? false),
            head: (bool) ($input['head'] ?? false),
        );

        [, $referrer, $landing] = self::ARRIVALS[$input['arrival']];

        // The Country dimension reads request headers, so the lab sets the one
        // Cloudflare would send.
        $request->headers->remove('CF-IPCountry');
        if (! empty($input['country'])) {
            $request->headers->set('CF-IPCountry', $input['country']);
        }

        $views = views($model)
            ->useVisitor($visitor)
            ->viewedBy($viewer)
            ->arrivedFrom(new Arrival(Arrival::hostOf($referrer), $landing))
            ->collection($input['collection'] ?? null)
            ->queue((bool) ($input['queue'] ?? false));

        if ($input['cooldown'] > 0) {
            $views->cooldown((int) $input['cooldown']);
        }

        if (! empty($input['note'])) {
            $views->context(['note' => $input['note']]);
        }

        return $views->attempt();
    }

    private function remember(Request $request, Post|Episode $model, array $input, RecordResult $result): void
    {
        $history = $request->session()->get('lab.history', []);

        array_unshift($history, [
            'at' => now()->format('H:i:s'),
            'title' => $model->title,
            'agent' => SimulatedVisitor::AGENTS[$input['agent']][0],
            'arrival' => self::ARRIVALS[$input['arrival']][0],
            'recorded' => $result->recorded,
            'queued' => $result->queued,
            'present' => $result->present,
            'guard' => Guards::name($result->skippedBy),
            'why' => Guards::explain($result->skippedBy),
            'dimensions' => $result->dimensions,
        ]);

        $request->session()->put('lab.history', array_slice($history, 0, 12));
    }

    private function visitorId(Request $request): string
    {
        if (! $request->session()->has('lab.visitor')) {
            $request->session()->put('lab.visitor', 'lab-'.Str::random(16));
        }

        return $request->session()->get('lab.visitor');
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function targets(): array
    {
        return [
            'Articles' => Post::query()->published()->latest('published_at')->pluck('title', 'slug')->mapWithKeys(fn ($title, $slug) => ["post:{$slug}" => $title])->all(),
            'Episodes' => Episode::query()->published()->latest('published_at')->pluck('title', 'slug')->mapWithKeys(fn ($title, $slug) => ["episode:{$slug}" => $title])->all(),
        ];
    }

    private function resolve(string $target): Post|Episode
    {
        [$type, $slug] = explode(':', $target, 2) + [1 => ''];

        return ($type === 'episode' ? Episode::query() : Post::query())->where('slug', $slug)->firstOrFail();
    }
}
