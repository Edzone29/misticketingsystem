<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketSubcategory extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_category_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'ticket_subcategory_id');
    }
}
