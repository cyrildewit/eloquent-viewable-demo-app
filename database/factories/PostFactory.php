<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(5), '.');

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($title).'-'.Str::random(4),
            'title' => $title,
            'topic' => $this->faker->randomElement(['laravel', 'databases', 'devops', 'frontend', 'craft']),
            'excerpt' => $this->faker->sentence(14),
            'body' => $this->faker->paragraphs(4, true),
            'hue' => $this->faker->numberBetween(0, 359),
            'reading_minutes' => $this->faker->numberBetween(3, 15),
            'published_at' => now()->subDays($this->faker->numberBetween(1, 60)),
        ];
    }
}
