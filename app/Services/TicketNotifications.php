<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketEventNotification;

class TicketNotifications
{
    public function newTicket(Ticket $ticket): void
    {
        $recipients = User::where('is_active', true)->whereHas('role.permissions', fn ($q) => $q->where('name', 'tickets.view_all'))->get();
        $this->send($recipients, 'ticket.created', $ticket, 'A new service request was submitted.', 'ticket-created-'.$ticket->id);
    }

    public function assigned(Ticket $ticket, User $technician, int $assignmentId): void
    {
        $this->send(collect([$technician]), 'ticket.assigned', $ticket, 'You have been assigned this service request.', 'ticket-assigned-'.$assignmentId);
    }

    public function statusChanged(Ticket $ticket, int $historyId): void
    {
        $recipients = collect([$ticket->requester]);
        $technician = $ticket->activeAssignment?->technician;
        if ($technician) {
            $recipients->push($technician);
        }
        $this->send($recipients->unique('id'), 'ticket.status_changed', $ticket, 'The ticket status is now '.$ticket->status.'.', 'ticket-status-'.$historyId);
    }

    public function commentAdded(Ticket $ticket, int $commentId, bool $internal): void
    {
        if ($internal) {
            $recipients = User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($ticket) {
                    $query->whereHas('role', fn ($role) => $role->where('name', 'system_administrator'))
                        ->orWhereHas('role.permissions', fn ($permission) => $permission->where('name', 'tickets.view_all'))
                        ->orWhereHas('assignments', fn ($assignment) => $assignment->where('ticket_id', $ticket->id));
                })
                ->where(function ($query) {
                    $query->whereHas('role', fn ($role) => $role->where('name', 'system_administrator'))
                        ->orWhereHas('role.permissions', fn ($permission) => $permission->where('name', 'tickets.update'));
                })
                ->get();
        } else {
            $recipients = collect([$ticket->requester, $ticket->activeAssignment?->technician])->filter();
        }

        $this->send($recipients->unique('id')->reject(fn ($user) => $user->id === auth()->id()), $internal ? 'ticket.internal_note' : 'ticket.reply', $ticket, $internal ? 'A staff-only note was added.' : 'A new reply was added.', 'ticket-comment-'.$commentId);
    }

    private function send(iterable $recipients, string $event, Ticket $ticket, string $message, string $eventKey): void
    {
        foreach ($recipients as $recipient) {
            $notification = new TicketEventNotification($event, $ticket->ticket_number, $message, route('tickets.show', $ticket), $eventKey, (int) $recipient->id);
            if (! $recipient->notifications()->whereKey($notification->id)->exists()) {
                $recipient->notify($notification);
            }
        }
    }
}
