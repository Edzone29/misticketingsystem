<?php

namespace App\Http\Controllers;

use App\Exports\AssetsExport;
use App\Exports\MaintenanceExport;
use App\Exports\TicketsExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\MaintenanceSchedule;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $tickets = $this->query($request)->with(['requester', 'department', 'category', 'priority', 'activeAssignment.technician', 'latestAssignment.technician'])->latest()->paginate(25)->withQueryString();
        $base = $this->query($request);
        $summary = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $asOf = now();
        $sla = (clone $base)
            ->join('ticket_sla_records', 'ticket_sla_records.ticket_id', '=', 'tickets.id')
            ->selectRaw('SUM(CASE WHEN ticket_sla_records.response_met_at IS NOT NULL OR (ticket_sla_records.paused_at IS NULL AND ticket_sla_records.response_due_at < ?) THEN 1 ELSE 0 END) as response_evaluated, SUM(CASE WHEN ticket_sla_records.response_met_at IS NOT NULL AND ticket_sla_records.response_met_at <= ticket_sla_records.response_due_at THEN 1 ELSE 0 END) as response_met, SUM(CASE WHEN ticket_sla_records.resolution_met_at IS NOT NULL OR (ticket_sla_records.paused_at IS NULL AND ticket_sla_records.resolution_due_at < ?) THEN 1 ELSE 0 END) as resolution_evaluated, SUM(CASE WHEN ticket_sla_records.resolution_met_at IS NOT NULL AND ticket_sla_records.resolution_met_at <= ticket_sla_records.resolution_due_at THEN 1 ELSE 0 END) as resolution_met', [$asOf, $asOf])
            ->first();
        $feedback = (clone $base)->join('feedback', 'feedback.ticket_id', '=', 'tickets.id')->selectRaw('COUNT(feedback.id) as rated, AVG(feedback.rating) as average_rating')->first();
        $departmentSummary = $this->breakdown($base, 'departments', 'department_id');
        $categorySummary = $this->breakdown($base, 'ticket_categories', 'ticket_category_id');
        $prioritySummary = $this->breakdown($base, 'ticket_priorities', 'ticket_priority_id');
        $technicianSummary = User::whereHas('role', fn ($query) => $query->where('name', 'mis_technician'))
            ->orderBy('last_name')
            ->get(['id', 'name'])
            ->map(function (User $technician) use ($base) {
                $tickets = (clone $base)->whereHas('assignments', fn ($query) => $query->where('technician_id', $technician->id))
                    ->get(['tickets.id', 'tickets.created_at', 'tickets.first_response_at', 'tickets.resolved_at']);
                $responded = $tickets->filter(fn (Ticket $ticket) => $ticket->first_response_at !== null);
                $resolved = $tickets->filter(fn (Ticket $ticket) => $ticket->resolved_at !== null);

                return [
                    'name' => $technician->name,
                    'assigned' => $tickets->count(),
                    'completed' => $resolved->count(),
                    'average_response_hours' => $this->averageHours($responded, 'first_response_at'),
                    'average_resolution_hours' => $this->averageHours($resolved, 'resolved_at'),
                ];
            })
            ->sortByDesc('assigned')
            ->values();
        $periodSummary = $this->periodSummary($base, $request->input('period_type', 'monthly'));

        return view('reports.index', ['tickets' => $tickets, 'summary' => $summary, 'departments' => Department::orderBy('name')->get(), 'categories' => TicketCategory::orderBy('name')->get(), 'priorities' => TicketPriority::orderBy('sort_order')->get(), 'technicians' => User::whereHas('role', fn ($q) => $q->where('name', 'mis_technician'))->orderBy('last_name')->get(), 'departmentSummary' => $departmentSummary, 'categorySummary' => $categorySummary, 'prioritySummary' => $prioritySummary, 'technicianSummary' => $technicianSummary, 'periodSummary' => $periodSummary, 'periodType' => $request->input('period_type', 'monthly'), 'sla' => $sla, 'feedback' => $feedback]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request)->with(['requester', 'department', 'category', 'priority', 'activeAssignment.technician', 'latestAssignment.technician']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ticket number', 'Submitted', 'Requester', 'Department', 'Category', 'Priority', 'Status', 'Technician', 'Subject', 'Resolution']);
            $query->orderBy('id')->chunk(500, function ($tickets) use ($out) {
                foreach ($tickets as $t) {
                    $row = [$t->ticket_number, $t->created_at->toIso8601String(), $t->requester->name, $t->department?->name, $t->category->name, $t->priority->name, $t->status, $t->activeAssignment?->technician?->name ?? $t->latestAssignment?->technician?->name, $t->subject, $t->resolution_details];
                    fputcsv($out, array_map(fn ($value) => $this->safeCell($value), $row));
                }
            });
            fclose($out);
        }, 'umis-ticket-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExcel(Request $request)
    {
        $query = $this->query($request)->with(['requester', 'department', 'category', 'priority', 'activeAssignment.technician', 'latestAssignment.technician'])->orderBy('id');

        return Excel::download(new TicketsExport($query), 'umis-ticket-report-'.now()->format('Ymd-His').'.xlsx', null, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $tickets = $this->query($request)->with(['requester', 'department', 'category', 'priority', 'activeAssignment.technician', 'latestAssignment.technician'])->orderBy('id')->limit(5000)->get();

        return Pdf::loadView('reports.export-pdf', compact('tickets'))->setPaper('legal', 'landscape')->download('umis-ticket-report-'.now()->format('Ymd-His').'.pdf');
    }

    public function assets(Request $request)
    {
        $assets = $this->assetQuery($request)->latest('asset_tag')->paginate(25)->withQueryString();
        $summary = (clone $this->assetQuery($request))->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $departments = Department::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();

        return view('reports.assets', compact('assets', 'summary', 'departments', 'categories'));
    }

    public function assetCsv(Request $request): StreamedResponse
    {
        $query = $this->assetQuery($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Asset tag', 'Equipment', 'Category', 'Serial number', 'Brand', 'Model', 'Department', 'Assigned user', 'Building', 'Room', 'Condition', 'Status', 'Acquired', 'Warranty expires', 'Remarks']);
            $query->chunk(500, function ($assets) use ($out) {
                foreach ($assets as $asset) {
                    fputcsv($out, array_map(fn ($value) => $this->safeCell($value), [
                        $asset->asset_tag, $asset->name, $asset->category?->name, $asset->serial_number, $asset->brand, $asset->model,
                        $asset->department?->name, $asset->assignedUser?->name, $asset->building, $asset->room_number, $asset->condition,
                        $asset->status, $asset->acquired_on?->toDateString(), $asset->warranty_expires_on?->toDateString(), $asset->remarks,
                    ]));
                }
            });
            fclose($out);
        }, 'umis-asset-inventory-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function assetExcel(Request $request)
    {
        return Excel::download(new AssetsExport($this->assetQuery($request)->orderBy('id')), 'umis-asset-inventory-'.now()->format('Ymd-His').'.xlsx', null, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function assetPdf(Request $request)
    {
        $assets = $this->assetQuery($request)->orderBy('asset_tag')->limit(5000)->get();

        return Pdf::loadView('reports.assets-pdf', compact('assets'))->setPaper('legal', 'landscape')->download('umis-asset-inventory-'.now()->format('Ymd-His').'.pdf');
    }

    public function maintenance(Request $request)
    {
        $schedules = $this->maintenanceQuery($request)->orderByDesc('scheduled_for')->paginate(25)->withQueryString();
        $summary = (clone $this->maintenanceQuery($request))->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $assets = Asset::orderBy('asset_tag')->get(['id', 'asset_tag', 'name']);
        $technicians = User::whereHas('role', fn ($query) => $query->where('name', 'mis_technician'))->orderBy('last_name')->get(['id', 'name']);
        $departments = Department::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();

        return view('reports.maintenance', compact('schedules', 'summary', 'assets', 'technicians', 'departments', 'categories'));
    }

    public function maintenanceCsv(Request $request): StreamedResponse
    {
        $query = $this->maintenanceQuery($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Scheduled date', 'Asset tag', 'Equipment', 'Task', 'Technician', 'Status', 'Findings', 'Actions performed', 'Performed at', 'Next maintenance']);
            $query->chunk(500, function ($schedules) use ($out) {
                foreach ($schedules as $schedule) {
                    fputcsv($out, array_map(fn ($value) => $this->safeCell($value), [
                        $schedule->scheduled_for?->toDateString(), $schedule->asset?->asset_tag, $schedule->asset?->name,
                        $schedule->title, $schedule->technician?->name, $schedule->status, $schedule->record?->findings,
                        $schedule->record?->actions_performed, $schedule->record?->performed_at?->toIso8601String(),
                        $schedule->record?->next_maintenance_on?->toDateString(),
                    ]));
                }
            });
            fclose($out);
        }, 'umis-maintenance-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function maintenanceExcel(Request $request)
    {
        return Excel::download(new MaintenanceExport($this->maintenanceQuery($request)->orderBy('id')), 'umis-maintenance-report-'.now()->format('Ymd-His').'.xlsx', null, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function maintenancePdf(Request $request)
    {
        $schedules = $this->maintenanceQuery($request)->orderBy('scheduled_for')->limit(5000)->get();

        return Pdf::loadView('reports.maintenance-pdf', compact('schedules'))->setPaper('legal', 'landscape')->download('umis-maintenance-report-'.now()->format('Ymd-His').'.pdf');
    }

    private function assetQuery(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'condition' => ['nullable', Rule::in(['New', 'Good', 'Fair', 'Needs Repair', 'Unserviceable'])],
            'status' => ['nullable', Rule::in(['In Service', 'Under Repair', 'In Storage', 'Retired'])],
        ]);

        return Asset::query()->with(['category', 'department', 'assignedUser'])
            ->when($request->search, fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('asset_tag', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('serial_number', 'like', "%{$term}%")))
            ->when($request->department_id, fn ($query, $id) => $query->where('department_id', $id))
            ->when($request->asset_category_id, fn ($query, $id) => $query->where('asset_category_id', $id))
            ->when($request->condition, fn ($query, $condition) => $query->where('condition', $condition))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status));
    }

    private function maintenanceQuery(Request $request)
    {
        $request->validate([
            'scheduled_from' => ['nullable', 'date'],
            'scheduled_to' => ['nullable', 'date', 'after_or_equal:scheduled_from'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'status' => ['nullable', Rule::in(['Scheduled', 'In Progress', 'Completed', 'Cancelled'])],
        ]);

        return MaintenanceSchedule::query()->with(['asset.category', 'asset.department', 'technician', 'record'])
            ->when($request->scheduled_from, fn ($query, $date) => $query->whereDate('scheduled_for', '>=', $date))
            ->when($request->scheduled_to, fn ($query, $date) => $query->whereDate('scheduled_for', '<=', $date))
            ->when($request->asset_id, fn ($query, $id) => $query->where('asset_id', $id))
            ->when($request->technician_id, fn ($query, $id) => $query->where('assigned_to', $id))
            ->when($request->department_id, fn ($query, $id) => $query->whereHas('asset', fn ($asset) => $asset->where('department_id', $id)))
            ->when($request->asset_category_id, fn ($query, $id) => $query->whereHas('asset', fn ($asset) => $asset->where('asset_category_id', $id)))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status));
    }

    private function query(Request $request)
    {
        $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'department_id' => ['nullable', 'exists:departments,id'], 'category_id' => ['nullable', 'exists:ticket_categories,id'], 'priority_id' => ['nullable', 'exists:ticket_priorities,id'], 'technician_id' => ['nullable', 'exists:users,id'], 'status' => ['nullable', Rule::in(['Open', 'Assigned', 'In Progress', 'Pending User', 'On Hold', 'Resolved', 'Closed', 'Reopened'])], 'overdue' => ['nullable', 'boolean']]);

        return Ticket::query()->when($request->start_date, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))->when($request->end_date, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id))->when($request->category_id, fn ($q, $id) => $q->where('ticket_category_id', $id))->when($request->priority_id, fn ($q, $id) => $q->where('ticket_priority_id', $id))->when($request->status, fn ($q, $status) => $q->where('status', $status))->when($request->technician_id, fn ($q, $id) => $q->whereHas('assignments', fn ($a) => $a->where('technician_id', $id)))->when($request->boolean('overdue'), fn ($q) => $q->whereNotIn('status', ['Resolved', 'Closed'])->whereHas('slaRecord', fn ($sla) => $sla->where('resolution_due_at', '<', now())));
    }

    private function averageHours(Collection $tickets, string $endTimestamp): string
    {
        if ($tickets->isEmpty()) {
            return 'N/A';
        }

        $minutes = $tickets->sum(fn (Ticket $ticket) => max(0, $ticket->created_at->diffInMinutes($ticket->{$endTimestamp}))) / $tickets->count();

        return number_format($minutes / 60, 1);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }

    private function breakdown($base, string $table, string $foreign): Collection
    {
        return (clone $base)->join($table, $table.'.id', '=', 'tickets.'.$foreign)->selectRaw($table.'.name as label, COUNT(tickets.id) as total')->groupBy($table.'.id', $table.'.name')->orderByDesc('total')->pluck('total', 'label');
    }

    private function periodSummary($base, string $type): Collection
    {
        $driver = DB::getDriverName();
        [$expression,$from,$to] = match ($type) {
            'daily' => [$driver === 'sqlite' ? "strftime('%Y-%m-%d', tickets.created_at)" : 'DATE(tickets.created_at)', today()->subDays(29)->startOfDay(), now()],
            'weekly' => [$driver === 'sqlite' ? "strftime('%Y-%W', tickets.created_at)" : "DATE_FORMAT(tickets.created_at, '%x-%v')", today()->startOfWeek()->subWeeks(11)->startOfDay(), now()],
            'annual' => [$driver === 'sqlite' ? "strftime('%Y', tickets.created_at)" : 'YEAR(tickets.created_at)', today()->startOfYear()->subYears(4)->startOfDay(), now()],
            default => [$driver === 'sqlite' ? "strftime('%Y-%m', tickets.created_at)" : "DATE_FORMAT(tickets.created_at, '%Y-%m')", today()->startOfMonth()->subMonths(11)->startOfDay(), now()],
        };

        return (clone $base)->whereBetween('tickets.created_at', [$from, $to])->selectRaw($expression.' as label, COUNT(tickets.id) as total')->groupBy('label')->orderBy('label')->pluck('total', 'label');
    }
}
