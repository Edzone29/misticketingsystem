<!doctype html>
<html lang="en"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#253247;font-size:9px}h1{color:#17365d;font-size:17px;margin:0 0 4px}.meta{color:#718096;margin-bottom:14px}table{border-collapse:collapse;width:100%}th{background:#17365d;color:#fff;font-size:8px;text-align:left}th,td{border:1px solid #dce2e9;padding:5px;vertical-align:top}tr:nth-child(even){background:#f5f7fa}.footer{margin-top:12px;color:#718096;font-size:8px}
</style></head><body>
<h1>{{ $universityName }} MIS — Preventive Maintenance</h1><div class="meta">Generated {{ now()->format('M j, Y g:i A') }} · {{ $schedules->count() }} matching schedules</div>
<table><thead><tr><th>Date</th><th>Asset</th><th>Task</th><th>Technician</th><th>Status</th><th>Findings</th><th>Actions</th><th>Next maintenance</th></tr></thead><tbody>
@foreach($schedules as $schedule)<tr><td>{{ $schedule->scheduled_for?->format('Y-m-d') }}</td><td>{{ $schedule->asset?->asset_tag }}<br>{{ $schedule->asset?->name }}</td><td>{{ $schedule->title }}</td><td>{{ $schedule->technician?->name }}</td><td>{{ $schedule->status }}</td><td>{{ $schedule->record?->findings }}</td><td>{{ $schedule->record?->actions_performed }}</td><td>{{ $schedule->record?->next_maintenance_on?->format('Y-m-d') }}</td></tr>@endforeach
</tbody></table><div class="footer">Confidential university operational record. Handle and retain according to institutional policy.</div>
</body></html>
