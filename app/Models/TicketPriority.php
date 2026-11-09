<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPriority extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'response_minutes', 'resolution_minutes', 'sort_order'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function slaPolicy()
    {
        return $this->hasOne(SlaPolicy::class);
    }
}
