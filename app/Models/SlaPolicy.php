<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_priority_id', 'response_minutes', 'resolution_minutes', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function priority()
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }
}
