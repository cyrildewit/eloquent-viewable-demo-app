<?php

namespace App\Jobs;

use App\Demo\FrontPage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Works out one reader's front page recommendations in Horizon, so the
 * request that asked for them never waits. Unique per reader: a page that
 * is reloaded while the job runs queues nothing new.
 */
class ComputeRecommendations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public ?int $userId,
        public ?string $visitor,
    ) {}

    public function uniqueId(): string
    {
        return FrontPage::recipientKey($this->userId, $this->visitor);
    }

    public function handle(FrontPage $front): void
    {
        $front->computeRecommendations($this->userId, $this->visitor);
    }
}
