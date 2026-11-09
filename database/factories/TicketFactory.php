<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return ['ticket_number' => 'MIS-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT), 'requester_id' => User::factory(), 'department_id' => Department::factory(), 'ticket_category_id' => TicketCategory::factory(), 'ticket_subcategory_id' => null, 'ticket_priority_id' => TicketPriority::factory(), 'status' => fake()->randomElement(['Open', 'Assigned', 'In Progress', 'Pending User', 'On Hold', 'Resolved', 'Closed', 'Reopened']), 'subject' => fake()->sentence(6), 'description' => fake()->paragraph(), 'building' => fake()->optional()->streetName(), 'room_number' => fake()->optional()->bothify('##??'), 'asset_number' => null, 'resolution_details' => null, 'first_response_at' => null, 'resolved_at' => null, 'closed_at' => null];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'Open', 'resolved_at' => null, 'closed_at' => null]);
    }
}
