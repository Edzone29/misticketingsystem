<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class AssetsExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Builder $assets) {}

    public function query(): Builder
    {
        return $this->assets;
    }

    public function headings(): array
    {
        return ['Asset tag', 'Equipment', 'Category', 'Serial number', 'Brand', 'Model', 'Department', 'Assigned user', 'Building', 'Room', 'Condition', 'Status', 'Acquired', 'Warranty expires', 'Remarks'];
    }

    public function title(): string
    {
        return 'Asset inventory';
    }

    public function map($asset): array
    {
        return array_map(fn ($value) => $this->safeCell($value), [
            $asset->asset_tag, $asset->name, $asset->category?->name, $asset->serial_number,
            $asset->brand, $asset->model, $asset->department?->name, $asset->assignedUser?->name,
            $asset->building, $asset->room_number, $asset->condition, $asset->status,
            $asset->acquired_on?->toDateString(), $asset->warranty_expires_on?->toDateString(), $asset->remarks,
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
