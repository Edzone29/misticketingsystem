<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-header">
                <div><h2>Ticket volume by period</h2><p>Counts use the current report filters.</p></div>
                <form method="GET" class="d-flex gap-2">
                    @foreach(request()->except('period_type','page') as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <label class="visually-hidden" for="period-type">Reporting period</label>
                    <select id="period-type" class="form-select form-select-sm" name="period_type"><option value="daily" @selected($periodType==='daily')>Daily - 30 days</option><option value="weekly" @selected($periodType==='weekly')>Weekly - 12 weeks</option><option value="monthly" @selected($periodType==='monthly')>Monthly - 12 months</option><option value="annual" @selected($periodType==='annual')>Annual - 5 years</option></select>
                    <button class="btn btn-sm btn-outline-primary">View</button>
                </form>
            </div>
            <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Period</th><th>Tickets</th></tr></thead><tbody>@forelse($periodSummary as $period=>$count)<tr><td>{{ $period }}</td><td>{{ $count }}</td></tr>@empty<tr><td colspan="2" class="text-muted">No ticket records in this period.</td></tr>@endforelse</tbody></table></div>
        </section>
    </div>
    <div class="col-lg-3 col-md-6">
        <section class="panel h-100"><div class="panel-header"><div><h2>SLA compliance</h2><p>Tickets matching these filters</p></div></div><div class="panel-body pt-0">
            <div class="summary-label">Response met on time</div><div class="metric-value">{{ $sla?->response_evaluated ? number_format(100*(int)$sla->response_met/(int)$sla->response_evaluated,1).'%' : 'N/A' }}</div><div class="small text-muted">{{ (int)($sla?->response_met??0) }} of {{ (int)($sla?->response_evaluated??0) }} evaluated targets</div>
            <hr><div class="summary-label">Resolution met on time</div><div class="metric-value">{{ $sla?->resolution_evaluated ? number_format(100*(int)$sla->resolution_met/(int)$sla->resolution_evaluated,1).'%' : 'N/A' }}</div><div class="small text-muted">{{ (int)($sla?->resolution_met??0) }} of {{ (int)($sla?->resolution_evaluated??0) }} evaluated targets</div>
        </div></section>
    </div>
    <div class="col-lg-3 col-md-6">
        <section class="panel h-100"><div class="panel-header"><div><h2>User satisfaction</h2><p>Feedback for matching tickets</p></div></div><div class="panel-body pt-0"><div class="summary-label">Average rating</div><div class="metric-value">{{ $feedback?->rated ? number_format((float)$feedback->average_rating,1).' / 5' : 'N/A' }}</div><div class="small text-muted">{{ (int)($feedback?->rated??0) }} ratings received</div></div></section>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach([['Department',$departmentSummary],['Category',$categorySummary],['Priority',$prioritySummary]] as [$label,$data])
        <div class="col-xl-4 col-md-6"><section class="panel h-100"><div class="panel-header"><div><h2>By {{ $label }}</h2><p>Matching ticket count</p></div></div><div class="table-responsive"><table class="table table-sm mb-0"><tbody>@forelse($data as $name=>$count)<tr><td>{{ $name }}</td><td class="text-end fw-semibold">{{ $count }}</td></tr>@empty<tr><td class="text-muted">No records.</td></tr>@endforelse</tbody></table></div></section></div>
    @endforeach
    <div class="col-xl-12"><section class="panel"><div class="panel-header"><div><h2>Technician performance</h2><p>Ticket assignments and average elapsed response and resolution time</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Technician</th><th>Assigned tickets</th><th>Completed tickets</th><th>Average response (hrs)</th><th>Average resolution (hrs)</th></tr></thead><tbody>
        @forelse($technicianSummary as $technician)<tr><td>{{ $technician['name'] }}</td><td>{{ $technician['assigned'] }}</td><td>{{ $technician['completed'] }}</td><td>{{ $technician['average_response_hours'] }}</td><td>{{ $technician['average_resolution_hours'] }}</td></tr>
        @empty<tr><td colspan="5"><div class="empty-state">No technician records match these filters.</div></td></tr>@endforelse
    </tbody></table></div></section></div>
</div>
