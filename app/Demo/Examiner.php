<?php

namespace App\Demo;

use App\Models\DemoEvent;
use App\Models\Post;
use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Reads the tables the package keeps, so the Console can show what a
 * maintenance command changed. Plain queries on purpose: this is what you
 * would look at in a database client.
 */
final class Examiner
{
    /**
     * The numbers the Console compares before and after a command.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $views = DB::table('views');

        return [
            'views' => (clone $views)->count(),
            'anonymised' => (clone $views)->where('visitor', 'like', 'a:%')->count(),
            'with viewer' => (clone $views)->whereNotNull('viewer_id')->count(),
            'rollup rows' => DB::table($this->rollupsTable())->count(),
            'pairs' => DB::table(config('eloquent-viewable.querying.pairs.table'))->count(),
            'milestone marks' => DB::table(config('eloquent-viewable.milestones.table'))->count(),
            'spiking' => DB::table(config('eloquent-viewable.spikes.table'))->count(),
            'events' => DemoEvent::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function views(): array
    {
        $views = DB::table('views');
        $anonymiseAfter = config('eloquent-viewable.retention.anonymise.after');
        $pruneAfter = config('eloquent-viewable.retention.prune.after');

        return [
            'total' => (clone $views)->count(),
            'oldest' => (clone $views)->min('viewed_at'),
            'newest' => (clone $views)->max('viewed_at'),
            'anonymised' => (clone $views)->where('visitor', 'like', 'a:%')->count(),
            'with_viewer' => (clone $views)->whereNotNull('viewer_id')->count(),
            'with_context' => (clone $views)->whereNotNull('context')->count(),
            'anonymise_after' => $anonymiseAfter,
            'prune_after' => $pruneAfter,
            'to_anonymise' => $anonymiseAfter ? (clone $views)->where('viewed_at', '<', $this->ago($anonymiseAfter))->where(fn ($q) => $q->where('visitor', 'not like', 'a:%')->orWhereNull('visitor'))->count() : null,
            'to_prune' => $pruneAfter ? (clone $views)->where('viewed_at', '<', $this->ago($pruneAfter))->count() : null,
            'sample' => (clone $views)->orderByDesc('id')->limit(6)->get(),
        ];
    }

    /**
     * Per month of the last two years: the rows still in the views table,
     * and the views the day and month tiers of the built-in rollup hold for
     * that month. Shows where each part of the history lives.
     *
     * @return Collection<int, array{month: string, raw: int, day: int, month_tier: int, hour: int}>
     */
    public function timeline(): Collection
    {
        $from = CarbonImmutable::now()->startOfMonth()->subMonths(24);
        $month = $this->monthExpression('viewed_at');
        $bucketMonth = $this->monthExpression('bucket_start');

        $raw = DB::table('views')
            ->selectRaw("{$month} as month, count(*) as aggregate")
            ->where('viewed_at', '>=', $from)
            ->groupBy('month')
            ->pluck('aggregate', 'month');

        $tiers = DB::table($this->rollupsTable())
            ->selectRaw("{$bucketMonth} as month, tier, sum(views) as aggregate")
            ->where('rollup', 'views')
            ->where('grouping', 'type')
            ->whereNull('dimension')
            ->where('bucket_start', '>=', $from)
            ->groupBy('month', 'tier')
            ->get()
            ->groupBy('month');

        return collect(range(0, 24))->map(function (int $i) use ($from, $raw, $tiers): array {
            $key = $from->addMonths($i)->format('Y-m');
            $tier = ($tiers[$key] ?? collect())->pluck('aggregate', 'tier');

            return [
                'month' => $key,
                'raw' => (int) ($raw[$key] ?? 0),
                'hour' => (int) ($tier['hour'] ?? 0),
                'day' => (int) ($tier['day'] ?? 0),
                'month_tier' => (int) ($tier['month'] ?? 0),
            ];
        });
    }

    /**
     * @return Collection<int, object>
     */
    public function rollups(): Collection
    {
        $grammar = DB::connection()->getQueryGrammar();
        [$grouping, $rows, $values] = [$grammar->wrap('grouping'), $grammar->wrap('rows'), $grammar->wrap('values')];

        return DB::table($this->rollupsTable())
            ->selectRaw("rollup, tier, {$grouping}, count(*) as {$rows}, count(distinct bucket_start) as buckets, min(bucket_start) as first_bucket, max(bucket_start) as last_bucket, sum(views) as views, count(distinct dimension) as {$values}")
            ->groupBy('rollup', 'tier', 'grouping')
            ->orderBy('rollup')
            ->orderByRaw("case tier when 'hour' then 1 when 'day' then 2 when 'month' then 3 else 4 end")
            ->orderBy('grouping')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function state(): Collection
    {
        return DB::table('view_state')->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function pairs(): array
    {
        $table = config('eloquent-viewable.querying.pairs.table');

        return [
            'rows' => DB::table($table)->count(),
            'strongest' => DB::table($table)->orderByDesc('visitors')->limit(5)->get(),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function milestones(): Collection
    {
        return DB::table(config('eloquent-viewable.milestones.table'))->orderByDesc('high_water')->limit(8)->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function spikes(): Collection
    {
        return DB::table(config('eloquent-viewable.spikes.table'))->orderByDesc('since')->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function counters(): Collection
    {
        return Post::query()->published()->orderByDesc('total_views')->limit(6)->get();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function infrastructure(): array
    {
        $stream = config('eloquent-viewable.recording.store.redis.stream');

        return [
            'queue' => rescue(fn () => Queue::size(), null, false),
            'stream' => rescue(fn () => Redis::connection(config('eloquent-viewable.recording.store.redis.connection'))->command('xlen', [$stream]), null, false),
            'redis' => rescue(fn () => Redis::connection()->command('ping') ? 'up' : 'down', 'down', false),
        ];
    }

    public function title(string $type, int|string $key): string
    {
        try {
            $class = Relation::getMorphedModel($type) ?? $type;

            return $class::query()->find($key)?->title ?? "{$type} #{$key}";
        } catch (Throwable) {
            return "{$type} #{$key}";
        }
    }

    private function rollupsTable(): string
    {
        return config('eloquent-viewable.retention.rollups.table');
    }

    private function ago(string $duration): CarbonImmutable
    {
        return CarbonImmutable::parse(Duration::tryParse($duration)?->before(CarbonImmutable::now()) ?? CarbonImmutable::now());
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "date_format({$column}, '%Y-%m')",
        };
    }
}
