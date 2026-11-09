<?php

namespace Database\Factories;

use App\Models\TicketNumberCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketNumberCounter> */
class TicketNumberCounterFactory extends Factory
{
    protected $model = TicketNumberCounter::class;

    public function definition(): array
    {
        return ['year' => fake()->unique()->numberBetween(2020, 2099), 'last_number' => fake()->numberBetween(0, 10000)];
    }
}
