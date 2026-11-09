@extends('layouts.app')
@section('title','Asset report')
@section('heading','Asset inventory report')
@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="{{ route('reports.assets.csv',request()->query()) }}">Download CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('reports.assets.excel',request()->query()) }}">Download Excel</a>
        <a class="btn btn-primary" href="{{ route('reports.assets.pdf',request()->query()) }}">Download PDF</a>
    </div>
@endsection
@section('content')
    <div class="report-tabs mb-3"><a href="{{ route('reports.tickets') }}">Tickets</a><a class="active" href="{{ route('reports.assets') }}">Asset inventory</a><a href="{{ route('reports.maintenance') }}">Maintenance</a></div>
    <section class="panel mb-4">
        <div class="panel-header"><div><h2>Filter inventory</h2><p>Filters are applied to the table and all downloads.</p></div></div>
        <form method="GET" class="panel-body pt-0"><div class="row g-2">
            <div class="col-md-4"><label class="form-label" for="asset-search">Search</label><input id="asset-search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Asset tag, name or serial number"></div>
            <div class="col-md-2"><label class="form-label" for="asset-department">Department</label><select id="asset-department" name="department_id" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id')==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="asset-category">Category</label><select id="asset-category" name="asset_category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('asset_category_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="asset-condition">Condition</label><select id="asset-condition" name="condition" class="form-select"><option value="">All conditions</option>@foreach(['New','Good','Fair','Needs Repair','Unserviceable'] as $condition)<option @selected(request('condition')===$condition)>{{ $condition }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="asset-status">Status</label><select id="asset-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['In Service','Under Repair','In Storage','Retired'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-12"><button class="btn btn-primary">Apply filters</button> <a class="btn btn-outline-secondary" href="{{ route('reports.assets') }}">Clear</a></div>
        </div></form>
    </section>
    <div class="metric-grid mb-4"><article class="metric-card"><div class="metric-label">Matching assets</div><div class="metric-value">{{ number_format($assets->total()) }}</div></article>@foreach($summary as $status=>$count)<article class="metric-card"><div class="metric-label">{{ $status }}</div><div class="metric-value">{{ number_format($count) }}</div></article>@endforeach</div>
    <section class="panel"><div class="panel-header"><div><h2>Inventory records</h2><p>Asset records matching the filters</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Asset</th><th>Category</th><th>Department / user</th><th>Condition</th><th>Status</th><th>Warranty</th></tr></thead><tbody>
        @forelse($assets as $asset)<tr><td><a class="text-link fw-semibold" href="{{ route('assets.show',$asset) }}">{{ $asset->asset_tag }}</a><div>{{ $asset->name }}</div><small class="text-muted">Serial: {{ $asset->serial_number ?: '—' }}</small></td><td>{{ $asset->category?->name }}</td><td>{{ $asset->department?->name ?? 'Unassigned' }}<div class="small text-muted">{{ $asset->assignedUser?->name }}</div></td><td>{{ $asset->condition }}</td><td>{{ $asset->status }}</td><td>{{ $asset->warranty_expires_on?->format('M j, Y') ?? '—' }}</td></tr>
        @empty<tr><td colspan="6"><div class="empty-state">No assets match these filters.</div></td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $assets->links() }}</div></section>
@endsection
