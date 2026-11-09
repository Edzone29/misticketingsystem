<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketAssignment> */
class TicketAssignmentFactory extends Factory
{
    protected $model = TicketAssignment::class;

    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'technician_id' => User::factory()->technician(), 'assigned_by' => User::factory()->misHead(), 'reason' => fake()->optional()->sentence(), 'assigned_at' => now(), 'ended_at' => null];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => ['ended_at' => now()]);
    }
}
