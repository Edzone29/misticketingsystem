<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use App\Models\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SlaPolicy> */
class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        return ['ticket_priority_id' => TicketPriority::factory(), 'response_minutes' => fake()->numberBetween(15, 480), 'resolution_minutes' => fake()->numberBetween(240, 5000), 'enabled' => true];
    }
}
