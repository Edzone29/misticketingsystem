<?php

namespace Database\Factories;

use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KnowledgeBaseArticle> */
class KnowledgeBaseArticleFactory extends Factory
{
    protected $model = KnowledgeBaseArticle::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return ['author_id' => User::factory(), 'title' => $title, 'slug' => fake()->unique()->slug(), 'category' => fake()->word(), 'body' => fake()->paragraphs(3, true), 'is_published' => false, 'published_at' => null];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => true, 'published_at' => now()]);
    }
}
