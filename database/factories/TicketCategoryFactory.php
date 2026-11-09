<?php

namespace Database\Factories;

use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketCategory> */
class TicketCategoryFactory extends Factory
{
    protected $model = TicketCategory::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'description' => fake()->optional()->sentence(), 'is_active' => true];
    }
}
