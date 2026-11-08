<?php

namespace App\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $total_views counter columns, written by views:recount
 * @property int $unique_viewers
 * @property int $views_last_week
 * @property int $google_views_30d
 * @property float $hot_score
 */
class Post extends Model implements Viewable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use InteractsWithViews;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'hot_score' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * A cover gradient made from the hue, so the demo needs no images.
     */
    public function cover(): string
    {
        $a = $this->hue;
        $b = ($this->hue + 48) % 360;

        return "background-image: linear-gradient(135deg, oklch(0.72 0.14 {$a}), oklch(0.46 0.16 {$b}))";
    }
}
