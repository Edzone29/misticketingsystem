<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Feedback> */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'user_id' => null, 'rating' => fake()->numberBetween(1, 5), 'comments' => fake()->optional()->sentence()];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Feedback $feedback) {
            if (! $feedback->user_id && $feedback->ticket) {
                $feedback->user_id = $feedback->ticket->requester_id;
            }
        });
    }
}
