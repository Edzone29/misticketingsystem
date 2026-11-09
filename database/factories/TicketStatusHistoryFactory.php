<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketStatusHistory> */
class TicketStatusHistoryFactory extends Factory
{
    protected $model = TicketStatusHistory::class;

    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'changed_by' => User::factory(), 'from_status' => null, 'to_status' => 'Open', 'reason' => 'Ticket submitted'];
    }
}
