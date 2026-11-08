<?php

namespace App\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A podcast episode, the second viewable type, so rankings across types and
 * collections ("page views" and "plays") have something to show.
 */
class Episode extends Model implements Viewable
{
    use InteractsWithViews;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function cover(): string
    {
        $a = $this->hue;
        $b = ($this->hue + 160) % 360;

        return "background-image: radial-gradient(circle at 30% 20%, oklch(0.8 0.15 {$a}), oklch(0.32 0.12 {$b}))";
    }
}
