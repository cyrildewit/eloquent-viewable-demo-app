<?php

namespace App\Demo;

use App\Viewable\Guards\IgnoreAuthors;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreMissingUserAgent;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreOptedOutViewers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;

/**
 * What each recording guard refuses, in the words the receipts use.
 */
final class Guards
{
    public const array REFUSES = [
        IgnoreCrawlers::class => 'The user agent belongs to a crawler.',
        IgnoreMissingUserAgent::class => 'The request has no user agent, like a script or a health check.',
        IgnoreIpAddresses::class => 'The IP address is in recording.ignored_ip_addresses.',
        IgnoreHeadRequests::class => 'A HEAD request receives no page, so nobody saw it.',
        IgnorePrefetch::class => 'The browser prefetched or prerendered the page ahead of a visit that may never come.',
        IgnoreOptedOutViewers::class => 'The reader turned off reading history, so tracksViews() returns false.',
        IgnoreAuthors::class => 'The author is reading their own post. A guard of this demo.',
        IgnoreBursts::class => 'This visitor opened more than '.'%max%'.' different models within %seconds% seconds and is blocked for a while.',
        EnforceCooldown::class => 'The same visitor viewed it moments ago, inside the cooldown.',
        ThrottleVisitors::class => 'The visitor recorded more than %throttle% views this minute.',
        IgnoreDoNotTrack::class => 'The browser sends DNT: 1.',
        IgnoreGlobalPrivacyControl::class => 'The browser sends Sec-GPC: 1.',
    ];

    public static function explain(?object $guard): ?string
    {
        if ($guard === null) {
            return null;
        }

        return strtr(self::REFUSES[$guard::class] ?? 'A guard refused the view.', [
            '%max%' => config('eloquent-viewable.recording.bursts.max'),
            '%seconds%' => config('eloquent-viewable.recording.bursts.seconds'),
            '%throttle%' => config('eloquent-viewable.recording.throttle.max_per_minute'),
        ]);
    }

    public static function name(?object $guard): ?string
    {
        return $guard === null ? null : class_basename($guard);
    }
}
