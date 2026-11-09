<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request, Ticket $ticket, Audit $audit)
    {
        abort_unless($request->user()->hasPermission('feedback.submit'), 403);
        abort_unless($ticket->requester_id === $request->user()->id && in_array($ticket->status, ['Resolved', 'Closed']), 403);
        abort_if($ticket->feedback()->exists(), 409, 'Feedback has already been submitted.');
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comments' => ['nullable', 'string', 'max:2000']]);
        $feedback = $ticket->feedback()->create(['user_id' => $request->user()->id] + $data);
        $audit->record('ticket.feedback_submitted', $ticket, ['rating' => $feedback->rating]);

        return back()->with('success', 'Thank you for your service feedback.');
    }
}
