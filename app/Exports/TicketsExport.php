<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class TicketsExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Builder $tickets) {}

    public function query(): Builder
    {
        return $this->tickets;
    }

    public function headings(): array
    {
        return ['Ticket number', 'Submitted', 'Requester', 'Department', 'Category', 'Priority', 'Status', 'Technician', 'Subject', 'Resolution'];
    }

    public function title(): string
    {
        return 'Ticket report';
    }

    public function map($ticket): array
    {
        return array_map(fn ($value) => $this->safeCell($value), [$ticket->ticket_number, $ticket->created_at?->toIso8601String(), $ticket->requester?->name, $ticket->department?->name, $ticket->category?->name, $ticket->priority?->name, $ticket->status, $ticket->activeAssignment?->technician?->name ?? $ticket->latestAssignment?->technician?->name, $ticket->subject, $ticket->resolution_details]);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
