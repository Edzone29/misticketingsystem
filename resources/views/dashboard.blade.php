@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('actions')
    @if(auth()->user()->hasPermission('tickets.create'))
        <a href="{{ route('tickets.create') }}" class="btn btn-primary">Submit a request</a>
    @endif
@endsection

@section('content')
    <section class="dashboard-intro" aria-label="Dashboard overview">
        <div>
            <div class="dashboard-role">{{ $dashboardRole }}</div>
            <p class="welcome-line mb-0">Welcome back, {{ auth()->user()->first_name }}. {{ $dashboardDescription }}</p>
        </div>
    </section>

    <div class="metric-grid" aria-label="Current service desk metrics">
        @foreach($metrics as $label => $value)
            <article class="metric-card">
                <div class="metric-label">{{ $label }}</div>
                <div class="metric-value">{{ is_numeric($value) ? number_format($value, is_float($value) ? 1 : 0) : $value }}</div>
                <div class="metric-note"><span class="metric-dot"></span>Live database data</div>
            </article>
        @endforeach
    </div>

    <div class="row g-4 mt-1">
        <div class="col-xl-7">
            <section class="panel h-100">
                <div class="panel-header">
                    <div>
                        <h2>Recent tickets</h2>
                        <p>Latest requests visible to your account</p>
                    </div>
                    <a href="{{ route('tickets.index') }}" class="text-link">View all</a>
                </div>
                @if($recent->isEmpty())
                    <div class="empty-state"><strong>No tickets yet</strong><span>Submitted or assigned requests will appear here.</span></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Priority</th></tr></thead>
                            <tbody>
                                @foreach($recent as $ticket)
                                    <tr>
                                        <td><a class="text-link fw-semibold" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                                        <td><div class="fw-medium">{{ \Illuminate\Support\Str::limit($ticket->subject, 34) }}</div><small class="text-muted">Updated {{ $ticket->updated_at->format('M j, Y') }}</small></td>
                                        <td><span class="status-badge status-{{ \Illuminate\Support\Str::slug($ticket->status) }}">{{ $ticket->status }}</span></td>
                                        <td><span class="priority-text priority-{{ strtolower($ticket->priority->name) }}">{{ $ticket->priority->name }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
        <div class="col-xl-5">
            <section class="panel h-100">
                <div class="panel-header">
                    <div><h2>Ticket activity</h2><p>Requests created in the last six months</p></div>
                </div>
                <div class="chart-wrap"><canvas id="ticketChart" role="img" aria-label="Ticket activity over the last six months"></canvas></div>
            </section>
        </div>
    </div>

    @if($recentUpdates->isNotEmpty())
        <section class="panel mt-4">
            <div class="panel-header"><div><h2>Recent updates</h2><p>Status changes for tickets visible to your account</p></div></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Ticket</th><th>Change</th><th>Updated by</th><th>Date</th></tr></thead>
                    <tbody>
                        @foreach($recentUpdates as $update)
                            <tr>
                                <td><a class="text-link fw-semibold" href="{{ route('tickets.show', $update->ticket) }}">{{ $update->ticket->ticket_number }}</a></td>
                                <td>{{ $update->from_status ?? 'Submitted' }} <span aria-hidden="true">→</span> {{ $update->to_status }}</td>
                                <td>{{ $update->changedBy?->name ?? 'System' }}</td>
                                <td>{{ $update->created_at->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($technicianWorkload->isNotEmpty() || $departmentStats->isNotEmpty())
        <div class="row g-4 mt-1">
            @if($technicianWorkload->isNotEmpty())
                <div class="col-xl-6">
                    <section class="panel">
                        <div class="panel-header"><div><h2>Technician workload</h2><p>Active assignments by technician</p></div></div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>Technician</th><th>Department</th><th>Active assignments</th></tr></thead>
                                <tbody>
                                    @foreach($technicianWorkload as $technician)
                                        <tr><td>{{ $technician->name }}</td><td>{{ $technician->department?->name ?? '—' }}</td><td>{{ $technician->active_assignments_count }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            @endif
            @if($departmentStats->isNotEmpty())
                <div class="col-xl-6">
                    <section class="panel">
                        <div class="panel-header"><div><h2>Department activity</h2><p>Active personnel and recorded tickets</p></div></div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>Department</th><th>Active users</th><th>Tickets</th></tr></thead>
                                <tbody>
                                    @foreach($departmentStats as $department)
                                        <tr><td>{{ $department->name }}</td><td>{{ $department->users_count }}</td><td>{{ $department->tickets_count }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const rows = @json($monthly);
        const labels = rows.map(row => new Date(row.year, row.month - 1).toLocaleString('en', { month: 'short', year: '2-digit' }));
        const chart = document.getElementById('ticketChart');
        new Chart(chart, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Tickets', data: rows.map(row => row.total), backgroundColor: '#315a83', borderRadius: 2, maxBarThickness: 32 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e9edf2' } }, x: { grid: { display: false } } } }
        });
    </script>
@endpush
