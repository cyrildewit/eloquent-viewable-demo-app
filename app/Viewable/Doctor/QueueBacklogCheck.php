<?php

namespace App\Viewable\Doctor;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use Illuminate\Contracts\Queue\Factory as Queue;

/**
 * A doctor check of the demo: queued views wait for the evd-horizon container.
 */
class QueueBacklogCheck implements Check
{
    public function __construct(
        protected Queue $queue,
    ) {}

    public function name(): string
    {
        return 'Demo queue';
    }

    public function run(): iterable
    {
        $connection = config('eloquent-viewable.recording.queue.connection') ?? config('queue.default');

        if ($connection === 'sync') {
            yield Finding::pass('Views are written during the request, the `sync` connection has no backlog.');

            return;
        }

        $waiting = $this->queue->connection($connection)->size();

        if ($waiting > 1_000) {
            yield Finding::warning("{$waiting} jobs wait on the `{$connection}` queue.", 'Start Horizon: `docker compose up -d horizon`, and watch it at /horizon.');

            return;
        }

        yield Finding::pass("{$waiting} jobs wait on the `{$connection}` queue.");
    }
}
