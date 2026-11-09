<?php

namespace App\Services;

use App\Models\TicketSlaRecord;
use App\Models\User;
use App\Notifications\TicketEventNotification;
use Illuminate\Support\Facades\DB;

class SlaMonitor
{
    public function run(): int
    {
        $settings = DB::table('system_settings')->pluck('value', 'key');
        $reminder = max(1, (int) ($settings['sla_reminder_minutes'] ?? 30));
        $escalation = max(0, (int) ($settings['sla_escalation_minutes'] ?? 60));
        $sent = 0;
        TicketSlaRecord::with(['ticket.requester', 'ticket.activeAssignment.technician'])->whereHas('ticket', fn ($q) => $q->whereNotIn('status', ['Resolved', 'Closed']))->chunkById(100, function ($records) use ($reminder, $escalation, &$sent) {
            $now = now();
            foreach ($records as $sla) {
                $ticket = $sla->ticket;
                if (! $ticket) {
                    continue;
                }
                if ($ticket->status === 'Pending User') {
                    $lastStatus = $ticket->statusHistory()->latest('id')->value('created_at');
                    if ($lastStatus && now()->parse($lastStatus)->lte($now->copy()->subDay())) {
                        $sent += $this->notify(collect([$ticket->requester]), 'ticket.pending_reminder', $ticket->ticket_number, 'This request is waiting for your response. Reply in the helpdesk to continue support.', $ticket, 'pending-reminder-'.$ticket->id.'-'.$now->toDateString());
                    }
                }
                if ($sla->paused_at) {
                    continue;
                }
                foreach (['response' => ['due' => $sla->response_due_at, 'met' => $sla->response_met_at], 'resolution' => ['due' => $sla->resolution_due_at, 'met' => $sla->resolution_met_at]] as $kind => $data) {
                    if ($data['met'] || ! $data['due']) {
                        continue;
                    }
                    $due = $data['due'];
                    $eventBase = 'sla-'.$kind.'-'.$sla->id.'-'.$due->timestamp;
                    if ($due->gt($now) && $due->lte($now->copy()->addMinutes($reminder))) {
                        $recipients = collect([$ticket->activeAssignment?->technician, $ticket->requester])->filter()->unique('id');
                        $sent += $this->notify($recipients, 'sla.warning', $ticket->ticket_number, "The {$kind} target for this ticket is due within {$reminder} minutes.", $ticket, $eventBase.'-warning');
                    } elseif ($due->lte($now->copy()->subMinutes($escalation))) {
                        $heads = User::where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('name', ['system_administrator', 'mis_head']))->get();
                        $sent += $this->notify($heads, 'sla.escalated', $ticket->ticket_number, "The {$kind} target for this ticket is overdue and requires supervisor review.", $ticket, $eventBase.'-escalated');
                    }
                }
            }
        });

        return $sent;
    }

    private function notify(iterable $recipients, string $event, string $ticketNumber, string $message, $ticket, string $key): int
    {
        $sent = 0;
        foreach ($recipients as $recipient) {
            $notification = new TicketEventNotification($event, $ticketNumber, $message, route('tickets.show', $ticket), $key, (int) $recipient->id);
            if (! $recipient->notifications()->whereKey($notification->id)->exists()) {
                $recipient->notify($notification);
                $sent++;
            }
        }

        return $sent;
    }
}
