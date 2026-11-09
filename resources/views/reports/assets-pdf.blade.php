<!doctype html>
<html lang="en"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#253247;font-size:9px}h1{color:#17365d;font-size:17px;margin:0 0 4px}.meta{color:#718096;margin-bottom:14px}table{border-collapse:collapse;width:100%}th{background:#17365d;color:#fff;font-size:8px;text-align:left}th,td{border:1px solid #dce2e9;padding:5px;vertical-align:top}tr:nth-child(even){background:#f5f7fa}.footer{margin-top:12px;color:#718096;font-size:8px}
</style></head><body>
<h1>{{ $universityName }} MIS — Asset Inventory</h1><div class="meta">Generated {{ now()->format('M j, Y g:i A') }} · {{ $assets->count() }} matching assets</div>
<table><thead><tr><th>Tag / equipment</th><th>Category</th><th>Serial</th><th>Department / user</th><th>Location</th><th>Condition</th><th>Status</th><th>Warranty</th></tr></thead><tbody>
@foreach($assets as $asset)<tr><td>{{ $asset->asset_tag }}<br>{{ $asset->name }}</td><td>{{ $asset->category?->name }}</td><td>{{ $asset->serial_number }}</td><td>{{ $asset->department?->name }}<br>{{ $asset->assignedUser?->name }}</td><td>{{ $asset->building }} {{ $asset->room_number }}</td><td>{{ $asset->condition }}</td><td>{{ $asset->status }}</td><td>{{ $asset->warranty_expires_on?->format('Y-m-d') }}</td></tr>@endforeach
</tbody></table><div class="footer">Confidential university operational record. Handle and retain according to institutional policy.</div>
</body></html>
