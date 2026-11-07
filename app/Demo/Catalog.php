<?php

namespace App\Demo;

/**
 * The content of Inkwell, the demo publication. Ages are in days before the
 * seed runs, and `pop` is how popular a piece is on a scale of 0 to 1, which
 * the traffic simulator turns into views.
 */
final class Catalog
{
    public const array TOPICS = [
        'laravel' => ['Laravel', 25],
        'databases' => ['Databases', 200],
        'devops' => ['DevOps', 150],
        'frontend' => ['Frontend', 300],
        'craft' => ['Craft', 60],
    ];

    /**
     * @return list<array{name: string, email: string, role: string, plan: string, bio: string, hue: int, topics: list<string>, tracks_reading?: bool}>
     */
    public static function people(): array
    {
        return [
            ['name' => 'Alice Moreau', 'email' => 'alice@inkwell.test', 'role' => 'author', 'plan' => 'pro', 'hue' => 15, 'topics' => ['laravel', 'databases'], 'bio' => 'Writes about Laravel internals and the queries that make apps slow.'],
            ['name' => 'Bob Okafor', 'email' => 'bob@inkwell.test', 'role' => 'author', 'plan' => 'free', 'hue' => 160, 'topics' => ['devops', 'frontend'], 'bio' => 'Ships to production on Fridays and writes about why that is fine.'],
            ['name' => 'Chloe Park', 'email' => 'chloe@inkwell.test', 'role' => 'reader', 'plan' => 'pro', 'hue' => 280, 'topics' => ['laravel', 'frontend'], 'bio' => 'Full-stack developer. Reads everything about Livewire.'],
            ['name' => 'Dan Ruiz', 'email' => 'dan@inkwell.test', 'role' => 'reader', 'plan' => 'free', 'hue' => 200, 'topics' => ['databases', 'devops'], 'bio' => 'Turned off reading history in his account settings.', 'tracks_reading' => false],
            ['name' => 'Eve Nakamura', 'email' => 'eve@inkwell.test', 'role' => 'reader', 'plan' => 'free', 'hue' => 330, 'topics' => ['craft', 'laravel'], 'bio' => 'New here. Signed up last week.'],
        ];
    }

    /**
     * @return list<array{title: string, topic: string, author: string, age: int|null, pop: float, minutes: int, excerpt: string}>
     */
    public static function posts(): array
    {
        return [
            ['title' => 'Breaking: the PHP 9 RFC passes', 'topic' => 'craft', 'author' => 'bob', 'age' => 1, 'pop' => 1.0, 'minutes' => 4, 'excerpt' => 'The vote closed overnight. Here is what changes for your Laravel app, and what does not.'],
            ['title' => 'The privacy-first analytics manifesto', 'topic' => 'craft', 'author' => 'alice', 'age' => 5, 'pop' => 0.7, 'minutes' => 7, 'excerpt' => 'You do not need a cookie banner to know which posts people read. Five rules we follow.'],
            ['title' => 'Redis streams as a write buffer', 'topic' => 'databases', 'author' => 'alice', 'age' => 11, 'pop' => 0.6, 'minutes' => 9, 'excerpt' => 'One XADD per request and one insert per thousand rows: buffering writes without losing them.'],
            ['title' => 'Partitioning a billion-row table', 'topic' => 'databases', 'author' => 'alice', 'age' => 21, 'pop' => 0.45, 'minutes' => 12, 'excerpt' => 'Dropping a month of data in a millisecond, and the primary key change that makes it possible.'],
            ['title' => 'Accessible charts without a library', 'topic' => 'frontend', 'author' => 'bob', 'age' => 33, 'pop' => 0.4, 'minutes' => 8, 'excerpt' => 'Inline SVG, a table fallback and a few ARIA attributes go a long way.'],
            ['title' => 'Window functions you will actually use', 'topic' => 'databases', 'author' => 'alice', 'age' => 47, 'pop' => 0.5, 'minutes' => 10, 'excerpt' => 'ROW_NUMBER, LAG and a running total: three patterns that replace a page of PHP.'],
            ['title' => 'Reading a flame graph', 'topic' => 'devops', 'author' => 'bob', 'age' => 62, 'pop' => 0.3, 'minutes' => 6, 'excerpt' => 'Wide is slow, tall is deep. A ten-minute introduction to profiling PHP in production.'],
            ['title' => 'Postgres or MySQL in 2026?', 'topic' => 'databases', 'author' => 'alice', 'age' => 93, 'pop' => 0.75, 'minutes' => 11, 'excerpt' => 'Both are excellent. The differences that still matter for a Laravel application.'],
            ['title' => 'Testing with Pest: a field guide', 'topic' => 'laravel', 'author' => 'alice', 'age' => 121, 'pop' => 0.6, 'minutes' => 14, 'excerpt' => 'Datasets, architecture tests and the expectations API, with the patterns we use every day.'],
            ['title' => 'Tailwind v4 in a Blade app', 'topic' => 'frontend', 'author' => 'bob', 'age' => 152, 'pop' => 0.7, 'minutes' => 7, 'excerpt' => 'CSS-first configuration, the new theme variables and what to delete from your old config.'],
            ['title' => 'Writing docs people finish', 'topic' => 'craft', 'author' => 'alice', 'age' => 181, 'pop' => 0.5, 'minutes' => 6, 'excerpt' => 'Lead with the answer, one idea per sentence, and cut every paragraph that starts with "Note".'],
            ['title' => 'What is new in Laravel 13', 'topic' => 'laravel', 'author' => 'bob', 'age' => 203, 'pop' => 0.85, 'minutes' => 9, 'excerpt' => 'Attributes on models, a slimmer skeleton and the upgrade steps that take an afternoon.'],
            ['title' => 'The boring guide to backups', 'topic' => 'devops', 'author' => 'bob', 'age' => 248, 'pop' => 0.4, 'minutes' => 8, 'excerpt' => 'A backup you never restored is a hope. A schedule, a restore drill and an alert.'],
            ['title' => 'Writing your first Laravel package', 'topic' => 'laravel', 'author' => 'alice', 'age' => 301, 'pop' => 0.5, 'minutes' => 15, 'excerpt' => 'Testbench, a service provider and the five files every package needs.'],
            ['title' => 'Livewire or Inertia: choosing in ten minutes', 'topic' => 'frontend', 'author' => 'bob', 'age' => 333, 'pop' => 0.8, 'minutes' => 10, 'excerpt' => 'Both are great. Four questions about your team that settle it.'],
            ['title' => 'How we review pull requests', 'topic' => 'craft', 'author' => 'bob', 'age' => 366, 'pop' => 0.4, 'minutes' => 7, 'excerpt' => 'Small diffs, a checklist and the comment we never leave.'],
            ['title' => 'Queues in Laravel, from first job to Horizon', 'topic' => 'laravel', 'author' => 'alice', 'age' => 402, 'pop' => 1.0, 'minutes' => 16, 'excerpt' => 'Dispatch, retry, batch and monitor: everything we learned running millions of jobs a day.'],
            ['title' => 'Dark mode done right', 'topic' => 'frontend', 'author' => 'bob', 'age' => 431, 'pop' => 0.5, 'minutes' => 6, 'excerpt' => 'Design tokens, prefers-color-scheme and a toggle that respects both.'],
            ['title' => 'Zero-downtime deploys on a single server', 'topic' => 'devops', 'author' => 'bob', 'age' => 498, 'pop' => 0.6, 'minutes' => 12, 'excerpt' => 'Symlinked releases, a health check and a rollback you can run half asleep.'],
            ['title' => 'Naming things, a practical approach', 'topic' => 'craft', 'author' => 'alice', 'age' => 552, 'pop' => 0.5, 'minutes' => 5, 'excerpt' => 'Name by what it does for the caller, not how it does it.'],
            ['title' => 'Ten Eloquent habits that keep queries fast', 'topic' => 'laravel', 'author' => 'alice', 'age' => 604, 'pop' => 0.9, 'minutes' => 11, 'excerpt' => 'Eager loading is the start. Nine more habits, each with the query it saves.'],
            ['title' => 'Docker for PHP developers', 'topic' => 'devops', 'author' => 'bob', 'age' => 655, 'pop' => 0.8, 'minutes' => 13, 'excerpt' => 'One Dockerfile, one compose file and the three volumes that make it fast on a Mac.'],
            ['title' => 'Indexes explained with a phone book', 'topic' => 'databases', 'author' => 'alice', 'age' => 702, 'pop' => 0.9, 'minutes' => 9, 'excerpt' => 'The analogy that finally makes composite indexes click, and where it breaks down.'],
            ['title' => 'Our 2027 roadmap', 'topic' => 'craft', 'author' => 'bob', 'age' => null, 'pop' => 0.0, 'minutes' => 5, 'excerpt' => 'A draft, not published yet, so it never shows up in a ranking.'],
        ];
    }

    /**
     * @return list<array{title: string, topic: string, guest: string, age: int, pop: float, minutes: int, summary: string}>
     */
    public static function episodes(): array
    {
        return [
            ['title' => 'Counting views at scale', 'topic' => 'databases', 'guest' => 'Priya Raman', 'age' => 3, 'pop' => 0.8, 'minutes' => 48, 'summary' => 'Rollups, Redis buffers and why every view is its own row until it is not.'],
            ['title' => 'Shipping on Fridays', 'topic' => 'devops', 'guest' => 'Marcus Lee', 'age' => 17, 'pop' => 0.6, 'minutes' => 41, 'summary' => 'Feature flags, small deploys and the culture that makes it boring.'],
            ['title' => 'The case for server-rendered apps', 'topic' => 'frontend', 'guest' => 'Ines Duarte', 'age' => 45, 'pop' => 0.7, 'minutes' => 52, 'summary' => 'Blade, Livewire and the JavaScript you no longer write.'],
            ['title' => 'Maintaining open source for ten years', 'topic' => 'craft', 'guest' => 'Tom Becker', 'age' => 80, 'pop' => 0.5, 'minutes' => 57, 'summary' => 'Saying no, writing upgrade guides and keeping the fun in it.'],
            ['title' => 'Eloquent under the hood', 'topic' => 'laravel', 'guest' => 'Sara Lindqvist', 'age' => 140, 'pop' => 0.9, 'minutes' => 63, 'summary' => 'From a query builder call to a hydrated model, step by step.'],
            ['title' => 'Postgres tips from the trenches', 'topic' => 'databases', 'guest' => 'Ken Watanabe', 'age' => 230, 'pop' => 0.6, 'minutes' => 45, 'summary' => 'EXPLAIN ANALYZE, partial indexes and the vacuum settings that matter.'],
            ['title' => 'Designing for dark mode', 'topic' => 'frontend', 'guest' => 'Lena Hoffmann', 'age' => 390, 'pop' => 0.4, 'minutes' => 38, 'summary' => 'Contrast, elevation and the colours that break at night.'],
            ['title' => 'From monolith to modular', 'topic' => 'laravel', 'guest' => 'Ade Bello', 'age' => 520, 'pop' => 0.7, 'minutes' => 55, 'summary' => 'Splitting a large Laravel app without a rewrite.'],
        ];
    }
}
