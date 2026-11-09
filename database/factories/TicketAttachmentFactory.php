<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketAttachment> */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function definition(): array
    {
        $name = fake()->word().'.txt';

        return ['ticket_id' => Ticket::factory(), 'uploaded_by' => User::factory(), 'ticket_comment_id' => null, 'disk' => 'local', 'path' => 'tickets/factory/'.$name, 'original_name' => $name, 'mime_type' => 'text/plain', 'size' => fake()->numberBetween(1, 4096)];
    }
}
