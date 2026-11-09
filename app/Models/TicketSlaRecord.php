<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketSlaRecord extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'response_due_at', 'resolution_due_at', 'response_met_at', 'resolution_met_at', 'paused_at', 'paused_minutes'];

    protected function casts(): array
    {
        return ['response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'response_met_at' => 'datetime', 'resolution_met_at' => 'datetime', 'paused_at' => 'datetime'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
