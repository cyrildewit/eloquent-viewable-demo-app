<?php

namespace App\Models;

use CyrildeWit\EloquentViewable\Concerns\HasViewHistory;
use CyrildeWit\EloquentViewable\Contracts\ViewerCanOptOut;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'plan', 'bio', 'hue', 'tracks_reading'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements ViewerCanOptOut
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasViewHistory;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tracks_reading' => 'boolean',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The "Don't record my reading history" switch on the account page. While
     * it is off, the IgnoreOptedOutViewers guard drops this reader's views.
     */
    public function tracksViews(): bool
    {
        return $this->tracks_reading;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->map(fn (string $part) => mb_substr($part, 0, 1))->take(2)->implode('');
    }
}
