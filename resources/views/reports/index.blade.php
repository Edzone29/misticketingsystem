@extends('layouts.app')
@section('title','Ticket reports')
@section('heading','Ticket reports')
@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="{{ route('reports.tickets.export',request()->query()) }}">Download CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('reports.tickets.export.xlsx',request()->query()) }}">Download Excel</a>
        <a class="btn btn-primary" href="{{ route('reports.tickets.export.pdf',request()->query()) }}">Download PDF</a>
    </div>
@endsection

@section('content')
    <div class="report-tabs mb-3">
        <a class="active" href="{{ route('reports.tickets') }}">Tickets</a>
        <a href="{{ route('reports.assets') }}">Asset inventory</a>
        <a href="{{ route('reports.maintenance') }}">Maintenance</a>
    </div>

    <section class="panel mb-4">
        <div class="panel-header"><div><h2>Filter records</h2><p>All results are generated from saved ticket records.</p></div></div>
        <form method="GET" class="panel-body pt-0">
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label" for="ticket-start-date">Start date</label><input id="ticket-start-date" type="date" class="form-control" name="start_date" value="{{ request('start_date') }}"></div>
                <div class="col-md-3"><label class="form-label" for="ticket-end-date">End date</label><input id="ticket-end-date" type="date" class="form-control" name="end_date" value="{{ request('end_date') }}"></div>
                <div class="col-md-3"><label class="form-label" for="ticket-department">Department</label><select id="ticket-department" name="department_id" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id')==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label" for="ticket-category">Category</label><select id="ticket-category" name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label" for="ticket-priority">Priority</label><select id="ticket-priority" name="priority_id" class="form-select"><option value="">All priorities</option>@foreach($priorities as $priority)<option value="{{ $priority->id }}" @selected(request('priority_id')==$priority->id)>{{ $priority->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label" for="ticket-status">Status</label><select id="ticket-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['Open','Assigned','In Progress','Pending User','On Hold','Resolved','Closed','Reopened'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label" for="ticket-technician">Technician</label><select id="ticket-technician" name="technician_id" class="form-select"><option value="">All technicians</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(request('technician_id')==$technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
                <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input id="ticket-overdue" type="checkbox" class="form-check-input" name="overdue" value="1" @checked(request('overdue'))><label class="form-check-label" for="ticket-overdue">Overdue only</label></div></div>
                <div class="col-12"><button class="btn btn-primary">Apply filters</button> <a class="btn btn-outline-secondary" href="{{ route('reports.tickets') }}">Clear</a></div>
            </div>
        </form>
    </section>

    <div class="metric-grid mb-4">
        <article class="metric-card"><div class="metric-label">Matching tickets</div><div class="metric-value">{{ number_format($tickets->total()) }}</div></article>
        @foreach($summary as $status=>$total)<article class="metric-card"><div class="metric-label">{{ $status }}</div><div class="metric-value">{{ number_format($total) }}</div></article>@endforeach
    </div>

    @include('reports.summary')

    <section class="panel">
        <div class="panel-header"><div><h2>Ticket records</h2><p>Filtered result set · {{ number_format($tickets->total()) }} tickets</p></div></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Requester / department</th><th>Category</th><th>Priority</th><th>Status</th><th>Technician</th><th>Submitted</th></tr></thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td><a class="text-link fw-semibold" href="{{ route('tickets.show',$ticket) }}">{{ $ticket->ticket_number }}</a></td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->requester->name }}<div class="small text-muted">{{ $ticket->department?->name ?? 'Unassigned' }}</div></td>
                        <td>{{ $ticket->category->name }}</td><td>{{ $ticket->priority->name }}</td>
                        <td><span class="status-badge status-{{ \Illuminate\Support\Str::slug($ticket->status) }}">{{ $ticket->status }}</span></td>
                        <td>{{ $ticket->activeAssignment?->technician?->name ?? $ticket->latestAssignment?->technician?->name ?? 'Unassigned' }}</td>
                        <td>{{ $ticket->created_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state">No tickets match these filters.</div></td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $tickets->links() }}</div>
    </section>
@endsection
