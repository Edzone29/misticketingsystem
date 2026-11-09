<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaintenanceExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Builder $schedules) {}

    public function query(): Builder
    {
        return $this->schedules;
    }

    public function headings(): array
    {
        return ['Scheduled date', 'Asset tag', 'Equipment', 'Task', 'Technician', 'Status', 'Findings', 'Actions performed', 'Performed at', 'Next maintenance'];
    }

    public function title(): string
    {
        return 'Maintenance report';
    }

    public function map($schedule): array
    {
        return array_map(fn ($value) => $this->safeCell($value), [
            $schedule->scheduled_for?->toDateString(), $schedule->asset?->asset_tag,
            $schedule->asset?->name, $schedule->title, $schedule->technician?->name,
            $schedule->status, $schedule->record?->findings, $schedule->record?->actions_performed,
            $schedule->record?->performed_at?->toIso8601String(),
            $schedule->record?->next_maintenance_on?->toDateString(),
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
