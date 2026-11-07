<?php

namespace App\Demo;

use Illuminate\Support\Facades\Cache;

/**
 * Whether the scheduler container runs the maintenance commands. Off after a
 * reset, so the data stays as seeded until you run a command yourself from
 * the Console, and you can examine every step.
 */
final class Autopilot
{
    private const string KEY = 'demo:autopilot';

    public static function enabled(): bool
    {
        return (bool) Cache::get(self::KEY, false);
    }

    public static function set(bool $enabled): void
    {
        Cache::forever(self::KEY, $enabled);
    }
}
