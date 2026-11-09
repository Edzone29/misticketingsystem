<?php

namespace Database\Factories;

use App\Models\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketPriority> */
class TicketPriorityFactory extends Factory
{
    protected $model = TicketPriority::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'response_minutes' => fake()->numberBetween(15, 480), 'resolution_minutes' => fake()->numberBetween(240, 5000), 'sort_order' => fake()->unique()->numberBetween(1, 255)];
    }
}
