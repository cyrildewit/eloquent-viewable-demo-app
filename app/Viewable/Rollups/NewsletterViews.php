<?php

namespace App\Viewable\Rollups;

use CyrildeWit\EloquentViewable\Querying\Rollups\Rollup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps the newsletter's reach per campaign for good, after the views it was
 * counted from are pruned. Read it with
 * `views($post)->rollup('newsletter')->countByDimension()`.
 */
final class NewsletterViews extends Rollup
{
    public string $name = 'newsletter';

    public function tiers(): array
    {
        return ['day' => '2y', 'month' => null];
    }

    public function filter(Builder $views): void
    {
        $views->getQuery()->where('source', 'Newsletter');
    }

    public function dimension(): ?string
    {
        return 'campaign';
    }
}
