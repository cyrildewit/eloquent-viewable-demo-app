<?php

namespace App\Viewable\Guards;

use App\Models\Post;
use App\Models\User;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * An author proofreading their own post is not a reader, so their views of
 * it are dropped. Sign in as Alice and open one of her posts to see it skip.
 */
final readonly class IgnoreAuthors implements RecordingGuard
{
    public function allows(ViewAttempt $attempt): bool
    {
        $viewer = $attempt->viewer ?? $attempt->visitor->viewer();

        if (! $viewer instanceof User || ! $attempt->viewable instanceof Post) {
            return true;
        }

        return $attempt->viewable->user_id !== $viewer->getKey();
    }
}
