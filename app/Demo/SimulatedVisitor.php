<?php

namespace App\Demo;

use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Database\Eloquent\Model;

/**
 * A visitor the Recording lab and the traffic simulator make up, passed to
 * `views($post)->useVisitor()`. Every guard reads the visitor through this
 * contract, so a made-up one goes through exactly the same checks as a real
 * request.
 */
final readonly class SimulatedVisitor implements Visitor
{
    public const array AGENTS = [
        'chrome' => ['Chrome on a Mac', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'],
        'iphone' => ['Safari on an iPhone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1'],
        'ipad' => ['Safari on an iPad', 'Mozilla/5.0 (iPad; CPU OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1'],
        'android' => ['Chrome on Android', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36'],
        'googlebot' => ['Googlebot', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
        'gptbot' => ['GPTBot', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'],
        'curl' => ['curl', 'curl/8.9.1'],
        'none' => ['No user agent', null],
    ];

    public function __construct(
        private string $id,
        private ?string $userAgent = self::AGENTS['chrome'][1],
        private ?string $ip = '203.0.113.10',
        private ?Model $viewer = null,
        private bool $doNotTrack = false,
        private bool $globalPrivacyControl = false,
        private bool $prefetch = false,
        private bool $head = false,
    ) {}

    public static function agent(string $key): ?string
    {
        return array_key_exists($key, self::AGENTS) ? self::AGENTS[$key][1] : self::AGENTS['chrome'][1];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function viewer(): ?Model
    {
        return $this->viewer;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function hasDoNotTrackHeader(): bool
    {
        return $this->doNotTrack;
    }

    public function hasGlobalPrivacyControl(): bool
    {
        return $this->globalPrivacyControl;
    }

    public function isPrefetch(): bool
    {
        return $this->prefetch;
    }

    public function isHeadRequest(): bool
    {
        return $this->head;
    }
}
