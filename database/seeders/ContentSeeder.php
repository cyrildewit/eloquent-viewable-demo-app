<?php

namespace Database\Seeders;

use App\Demo\Catalog;
use App\Models\Episode;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The publication itself: readers, articles and podcast episodes, without
 * any views.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $people = [];

        foreach (Catalog::people() as $person) {
            $people[Str::before($person['email'], '@')] = User::query()->create([
                'name' => $person['name'],
                'email' => $person['email'],
                'password' => 'password',
                'role' => $person['role'],
                'plan' => $person['plan'],
                'bio' => $person['bio'],
                'hue' => $person['hue'],
                'tracks_reading' => $person['tracks_reading'] ?? true,
            ]);
        }

        User::factory(20)->create();

        foreach (Catalog::posts() as $post) {
            Post::query()->create([
                'user_id' => $people[$post['author']]->id,
                'slug' => Str::slug($post['title']),
                'title' => $post['title'],
                'topic' => $post['topic'],
                'excerpt' => $post['excerpt'],
                'body' => $this->body(),
                'hue' => (Catalog::TOPICS[$post['topic']][1] + mt_rand(-25, 25) + 360) % 360,
                'reading_minutes' => $post['minutes'],
                'published_at' => match ($post['age']) {
                    null => null,
                    1 => now()->subHours(20)->startOfHour(),
                    default => now()->subDays($post['age'])->setTime(mt_rand(7, 11), mt_rand(0, 59)),
                },
            ]);
        }

        foreach (Catalog::episodes() as $i => $episode) {
            Episode::query()->create([
                'number' => count(Catalog::episodes()) - $i,
                'slug' => Str::slug($episode['title']),
                'title' => $episode['title'],
                'topic' => $episode['topic'],
                'summary' => $episode['summary'],
                'guest' => $episode['guest'],
                'hue' => (Catalog::TOPICS[$episode['topic']][1] + 40) % 360,
                'duration_minutes' => $episode['minutes'],
                'published_at' => now()->subDays($episode['age'])->setTime(6, 0),
            ]);
        }
    }

    private function body(): string
    {
        return collect(range(1, 6))
            ->map(fn () => fake()->paragraphs(mt_rand(2, 3), true))
            ->implode("\n\n");
    }
}
