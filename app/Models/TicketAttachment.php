<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'uploaded_by', 'ticket_comment_id', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function ticketComment()
    {
        return $this->belongsTo(TicketComment::class);
    }
}
