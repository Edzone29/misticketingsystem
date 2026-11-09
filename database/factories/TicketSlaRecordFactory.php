<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketSlaRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketSlaRecord> */
class TicketSlaRecordFactory extends Factory
{
    protected $model = TicketSlaRecord::class;

    public function definition(): array
    {
        $base = now();

        return ['ticket_id' => Ticket::factory(), 'response_due_at' => $base->copy()->addHours(4), 'resolution_due_at' => $base->copy()->addDays(2), 'response_met_at' => null, 'resolution_met_at' => null, 'paused_at' => null, 'paused_minutes' => 0];
    }
}
