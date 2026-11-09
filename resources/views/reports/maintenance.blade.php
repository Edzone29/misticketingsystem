@extends('layouts.app')
@section('title','Maintenance report')
@section('heading','Preventive maintenance report')
@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="{{ route('reports.maintenance.csv',request()->query()) }}">Download CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('reports.maintenance.excel',request()->query()) }}">Download Excel</a>
        <a class="btn btn-primary" href="{{ route('reports.maintenance.pdf',request()->query()) }}">Download PDF</a>
    </div>
@endsection
@section('content')
    <div class="report-tabs mb-3"><a href="{{ route('reports.tickets') }}">Tickets</a><a href="{{ route('reports.assets') }}">Asset inventory</a><a class="active" href="{{ route('reports.maintenance') }}">Maintenance</a></div>
    <section class="panel mb-4">
        <div class="panel-header"><div><h2>Filter maintenance</h2><p>Filters apply to the results and all downloads.</p></div></div>
        <form method="GET" class="panel-body pt-0"><div class="row g-2">
            <div class="col-md-2"><label class="form-label" for="scheduled-from">Scheduled from</label><input id="scheduled-from" type="date" class="form-control" name="scheduled_from" value="{{ request('scheduled_from') }}"></div>
            <div class="col-md-2"><label class="form-label" for="scheduled-to">Scheduled to</label><input id="scheduled-to" type="date" class="form-control" name="scheduled_to" value="{{ request('scheduled_to') }}"></div>
            <div class="col-md-2"><label class="form-label" for="maintenance-asset">Asset</label><select id="maintenance-asset" name="asset_id" class="form-select"><option value="">All assets</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected(request('asset_id')==$asset->id)>{{ $asset->asset_tag }} — {{ $asset->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="maintenance-technician">Technician</label><select id="maintenance-technician" name="technician_id" class="form-select"><option value="">All technicians</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(request('technician_id')==$technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="maintenance-department">Department</label><select id="maintenance-department" name="department_id" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id')==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="maintenance-category">Asset category</label><select id="maintenance-category" name="asset_category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('asset_category_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="maintenance-status">Status</label><select id="maintenance-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['Scheduled','In Progress','Completed','Cancelled'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-12"><button class="btn btn-primary">Apply filters</button> <a class="btn btn-outline-secondary" href="{{ route('reports.maintenance') }}">Clear</a></div>
        </div></form>
    </section>
    <div class="metric-grid mb-4"><article class="metric-card"><div class="metric-label">Matching schedules</div><div class="metric-value">{{ number_format($schedules->total()) }}</div></article>@foreach($summary as $status=>$count)<article class="metric-card"><div class="metric-label">{{ $status }}</div><div class="metric-value">{{ number_format($count) }}</div></article>@endforeach</div>
    <section class="panel"><div class="panel-header"><div><h2>Maintenance records</h2><p>Schedules, inspection findings and actions performed</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Scheduled</th><th>Asset</th><th>Task</th><th>Technician</th><th>Status</th><th>Inspection record</th></tr></thead><tbody>
        @forelse($schedules as $schedule)<tr><td>{{ $schedule->scheduled_for->format('M j, Y') }}</td><td>{{ $schedule->asset?->asset_tag }}<div class="small text-muted">{{ $schedule->asset?->name }}</div></td><td>{{ $schedule->title }}</td><td>{{ $schedule->technician?->name ?? 'Unassigned' }}</td><td>{{ $schedule->status }}</td><td>@if($schedule->record)<div><strong>Findings:</strong> {{ $schedule->record->findings }}</div><div><strong>Actions:</strong> {{ $schedule->record->actions_performed }}</div>@else<span class="text-muted">Not completed</span>@endif</td></tr>
        @empty<tr><td colspan="6"><div class="empty-state">No maintenance schedules match these filters.</div></td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $schedules->links() }}</div></section>
@endsection
