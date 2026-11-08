<?php

namespace App\Demo;

use App\Jobs\ComputeRecommendations;
use App\Models\Episode;
use App\Models\Post;
use App\Models\User;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The front page's view data, cached so no visitor waits for it.
 *
 * The shared panels are stale-while-revalidate: fresh for ten minutes, then
 * served stale for up to an hour while they recompute after the response.
 * Recommendations are never computed during a request: a miss shows the
 * trending fallback and queues the work, a stale list is shown while it
 * refreshes.
 *
 * Everything is cached as plain arrays of keys and numbers, because Laravel
 * refuses to unserialize objects from the cache (`cache.serializable_classes`).
 * The models are loaded again on read, one query per type.
 */
final class FrontPage
{
    public const int FreshSeconds = 600;

    public const int StaleSeconds = 3_600;

    private const string SharedKey = 'front:shared';

    /**
     * Trending, most read, rising, the trending fallback and episode plays.
     *
     * @return array{trending: Collection<int, object>, mostRead: Collection<int, object>, rising: Collection<int, object>, fallback: Collection<int, object>, plays: array<int, int>, at: string}
     */
    public function shared(): array
    {
        $shared = Cache::flexible(self::SharedKey, [self::FreshSeconds, self::StaleSeconds], fn (): array => $this->computeShared());

        return $this->hydrateShared($shared);
    }

    /**
     * Recomputes the shared panels now, for the warm job.
     */
    public function refreshShared(): void
    {
        $value = $this->computeShared();

        // The same two keys Cache::flexible() reads: the value and when it was made.
        Cache::putMany([
            self::SharedKey => $value,
            Repository::FLEXIBLE_CREATED_KEY_PREFIX.self::SharedKey => now()->getTimestamp(),
        ], self::StaleSeconds);
    }

    /**
     * The reader's recommendations from the cache, or null while the first
     * list is being worked out. A list older than FreshSeconds is returned
     * as is and refreshed in the background.
     *
     * @return Collection<int, object>|null
     */
    public function recommendations(?User $user, string $visitor): ?Collection
    {
        $key = self::recipientKey($user?->getKey(), $user === null ? $visitor : null);
        $cached = Cache::get($key);

        if ($cached === null && $user === null && ! View::query()->byVisitor($visitor)->exists()) {
            // A guest without views has nothing to recommend from; caching the
            // empty answer keeps crawlers from queueing a job per request.
            $cached = $this->storeRecommendations($key, []);
        }

        if ($cached === null || time() - $cached['at'] > self::FreshSeconds) {
            ComputeRecommendations::dispatch($user?->getKey(), $user === null ? $visitor : null);
        }

        return $cached === null ? null : $this->hydrateRecommendations($cached['items']);
    }

    /**
     * Works out a reader's recommendations and caches them. Runs in the queue.
     */
    public function computeRecommendations(?int $userId, ?string $visitor): void
    {
        $views = views(Post::class)->period(Period::pastDays(30));

        if ($userId !== null) {
            $views->viewedBy(User::query()->findOrFail($userId));
        } else {
            // Recommendations for a guest follow their visitor id; this is the
            // id of the browser that asked, carried into the queued job.
            $views->useVisitor(new SimulatedVisitor($visitor));
        }

        $items = [];

        foreach ($views->recommended(4) as $recommendation) {
            $items[] = [
                'type' => $recommendation->viewable->getMorphClass(),
                'id' => $recommendation->viewable->getKey(),
                'rank' => $recommendation->rank,
                'score' => $recommendation->score,
                'because' => $recommendation->because->map(fn ($model): array => [$model->getMorphClass(), $model->getKey()])->all(),
            ];
        }

        $this->storeRecommendations(self::recipientKey($userId, $visitor), $items);
    }

    public static function recipientKey(?int $userId, ?string $visitor): string
    {
        return 'front:recommendations:'.($userId !== null ? "user:{$userId}" : 'visitor:'.sha1((string) $visitor));
    }

    private function storeRecommendations(string $key, array $items): array
    {
        $value = ['at' => time(), 'items' => $items];

        Cache::put($key, $value, now()->addDay());

        return $value;
    }

    private function computeShared(): array
    {
        $episodes = Episode::query()->published()->latest('published_at')->limit(3)->get();

        return [
            'trending' => $this->flatten(views(Post::class)->trending(5)),
            'mostRead' => $this->flatten(Views::period(Period::pastDays(7))->top(5)),
            'rising' => $this->flatten(views(Post::class)->period(Period::pastDays(1))->rising(3, minimum: 5)),
            'plays' => Views::forViewables($episodes)->collection('plays')->counts()->all(),
            'at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{type: string, id: int|string, rank: int, count: int, score: ?float}>
     */
    private function flatten(iterable $ranking): array
    {
        $entries = [];

        foreach ($ranking as $entry) {
            $entries[] = ['type' => $entry->viewable->getMorphClass(), 'id' => $entry->viewable->getKey(), 'rank' => $entry->rank, 'count' => $entry->count, 'score' => $entry->score];
        }

        return $entries;
    }

    private function hydrateShared(array $shared): array
    {
        $models = $this->models([...$shared['trending'], ...$shared['mostRead'], ...$shared['rising']]);
        $entries = fn (array $list): Collection => collect($list)
            ->map(fn (array $entry): ?object => isset($models[$entry['type']][$entry['id']]) ? (object) [...$entry, 'viewable' => $models[$entry['type']][$entry['id']]] : null)
            ->filter()
            ->values();

        $trending = $entries($shared['trending']);

        return [
            'trending' => $trending,
            'mostRead' => $entries($shared['mostRead']),
            'rising' => $entries($shared['rising']),
            'fallback' => $trending->take(4),
            'plays' => $shared['plays'],
            'at' => $shared['at'],
        ];
    }

    private function hydrateRecommendations(array $items): Collection
    {
        $models = $this->models([...$items, ...collect($items)->flatMap(fn (array $item): array => array_map(fn (array $pair): array => ['type' => $pair[0], 'id' => $pair[1]], $item['because']))->all()]);

        return collect($items)
            ->filter(fn (array $item): bool => isset($models[$item['type']][$item['id']]))
            ->map(fn (array $item): object => (object) [
                'rank' => $item['rank'],
                'score' => $item['score'],
                'viewable' => $models[$item['type']][$item['id']],
                'because' => collect($item['because'])->map(fn (array $pair) => $models[$pair[0]][$pair[1]] ?? null)->filter()->values(),
            ])
            ->values();
    }

    /**
     * One query per type for every model a list refers to.
     *
     * @param  list<array{type: string, id: int|string}>  $refs
     * @return array<string, array<int|string, Model>>
     */
    private function models(array $refs): array
    {
        $models = [];

        foreach (collect($refs)->groupBy('type') as $type => $group) {
            $class = Relation::getMorphedModel($type) ?? $type;
            $models[$type] = $class::query()->whereKey($group->pluck('id')->unique()->all())->get()->keyBy(fn ($model) => $model->getKey())->all();
        }

        return $models;
    }
}
