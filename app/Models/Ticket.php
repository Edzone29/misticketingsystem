<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['ticket_number', 'requester_id', 'department_id', 'ticket_category_id', 'ticket_subcategory_id', 'ticket_priority_id', 'status', 'subject', 'description', 'building', 'room_number', 'asset_number', 'resolution_details', 'first_response_at', 'resolved_at', 'closed_at'];

    protected function casts(): array
    {
        return ['first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(TicketSubcategory::class, 'ticket_subcategory_id');
    }

    public function priority()
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function assignments()
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(TicketAssignment::class)->whereNull('ended_at')->latestOfMany();
    }

    public function latestAssignment()
    {
        return $this->hasOne(TicketAssignment::class)->latestOfMany('assigned_at');
    }

    public function statusHistory()
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function slaRecord()
    {
        return $this->hasOne(TicketSlaRecord::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'ticket_assets');
    }

    public function feedback()
    {
        return $this->hasOne(Feedback::class);
    }
}
