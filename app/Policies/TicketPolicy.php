<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view_all')
            || $user->hasPermission('tickets.view_assigned')
            || $user->hasPermission('tickets.view_own');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermission('tickets.view_all')) {
            return true;
        }

        if ($user->hasPermission('tickets.view_own') && $ticket->requester_id === $user->id) {
            return true;
        }

        return ($user->hasPermission('tickets.view_assigned') || $user->hasPermission('tickets.update'))
            && $ticket->assignments()
                ->where('technician_id', $user->id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function edit(User $user, Ticket $ticket): bool
    {
        if (in_array($ticket->status, ['Resolved', 'Closed'], true)) {
            return false;
        }

        if ($ticket->requester_id === $user->id
            && $user->hasPermission('tickets.view_own')
            && $ticket->status === 'Open') {
            return ! $ticket->assignments()->whereNull('ended_at')->exists();
        }

        if (! $user->hasPermission('tickets.update')) {
            return false;
        }

        if ($user->hasPermission('tickets.view_all')) {
            return true;
        }

        return $user->hasPermission('tickets.view_assigned')
            && $ticket->assignments()
                ->where('technician_id', $user->id)
                ->whereNull('ended_at')
                ->exists();
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.assign');
    }

    public function prioritize(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.prioritize');
    }

    public function updateStatus(User $user, Ticket $ticket, string $targetStatus): bool
    {
        $assignedTechnician = $user->hasPermission('tickets.update')
            && $ticket->assignments()
                ->where('technician_id', $user->id)
                ->whereNull('ended_at')
                ->exists();
        $authorizedStaff = $user->hasPermission('tickets.update')
            && ($user->hasPermission('tickets.view_all') || $assignedTechnician);

        return $authorizedStaff
            || ($ticket->requester_id === $user->id
                && $user->hasPermission('tickets.view_own')
                && in_array($targetStatus, ['Closed', 'Reopened'], true));
    }

    public function comment(User $user, Ticket $ticket, bool $internal): bool
    {
        return $this->view($user, $ticket)
            && (! $internal || $user->hasPermission('tickets.update'));
    }
}
