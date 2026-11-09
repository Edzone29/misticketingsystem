<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $canViewAll = $user->hasPermission('tickets.view_all');
        $isTechnician = $user->hasPermission('tickets.view_assigned') && ! $canViewAll;
        $isRequester = $user->hasPermission('tickets.view_own') && ! $canViewAll && ! $isTechnician;

        $tickets = Ticket::query();
        if ($canViewAll) {
            // Supervisors and administrators see the complete operational queue.
        } elseif ($isTechnician) {
            $tickets->whereHas('assignments', fn (Builder $query) => $query
                ->where('technician_id', $user->id));
        } elseif ($isRequester) {
            $tickets->where('requester_id', $user->id);
        } else {
            // An authenticated role without ticket visibility gets no ticket data.
            $tickets->whereRaw('1 = 0');
        }

        $counts = (clone $tickets)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recent = (clone $tickets)
            ->with(['requester', 'category', 'priority', 'activeAssignment.technician'])
            ->latest('updated_at')
            ->limit(8)
            ->get();
        $recentUpdates = TicketStatusHistory::query()
            ->whereHas('ticket', fn (Builder $query) => $query->whereIn('id', (clone $tickets)->select('id')))
            ->with(['ticket:id,ticket_number,subject', 'changedBy:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        $monthParts = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER) as year, CAST(strftime('%m', created_at) AS INTEGER) as month"
            : 'YEAR(created_at) as year, MONTH(created_at) as month';
        $monthly = (clone $tickets)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw($monthParts.', COUNT(*) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $metrics = [];
        $operationalTickets = clone $tickets;
        if ($isTechnician) {
            $operationalTickets->whereHas('assignments', fn (Builder $query) => $query
                ->where('technician_id', $user->id)
                ->whereNull('ended_at'));
        }
        $overdue = (clone $operationalTickets)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->whereHas('slaRecord', fn (Builder $query) => $query->where('resolution_due_at', '<', now()))
            ->count();

        $technicianWorkload = collect();
        $departmentStats = collect();
        $dashboardRole = 'Service desk';
        $dashboardDescription = 'Requests visible to your account.';

        if ($canViewAll) {
            $isAdministrator = $user->isRole('system_administrator');
            $dashboardRole = $isAdministrator ? 'System administration' : 'MIS operations';
            $dashboardDescription = $isAdministrator
                ? 'University helpdesk activity and system administration.'
                : 'Ticket queue, technician workload and service level performance.';

            $metrics = [
                'Total tickets' => (clone $tickets)->count(),
                'Open' => (int) ($counts['Open'] ?? 0),
                'Assigned' => (int) ($counts['Assigned'] ?? 0),
                'Unassigned' => (clone $tickets)
                    ->whereIn('status', ['Open', 'Reopened'])
                    ->whereDoesntHave('assignments', fn (Builder $query) => $query->whereNull('ended_at'))
                    ->count(),
                'In progress' => (int) ($counts['In Progress'] ?? 0),
                'Pending user' => (int) ($counts['Pending User'] ?? 0),
                'Resolved' => (int) ($counts['Resolved'] ?? 0),
                'Closed' => (int) ($counts['Closed'] ?? 0),
                'Reopened' => (int) ($counts['Reopened'] ?? 0),
                'Overdue' => $overdue,
                'SLA compliance' => $this->resolutionSlaCompliance(clone $tickets),
            ];

            if ($isAdministrator) {
                $metrics['Total users'] = User::count();
                $metrics['Total departments'] = Department::count();
                $metrics['Assets'] = Asset::where('status', '!=', 'Retired')->count();
            } else {
                $metrics['High priority'] = (clone $tickets)
                    ->whereHas('priority', fn (Builder $query) => $query->whereIn('name', ['Critical', 'High']))
                    ->whereNotIn('status', ['Resolved', 'Closed'])
                    ->count();
                $metrics['Average response (hrs)'] = $this->averageHours(
                    (clone $tickets)->whereNotNull('first_response_at')->get(['created_at', 'first_response_at']),
                    'first_response_at'
                );
                $metrics['Average resolution (hrs)'] = $this->averageHours(
                    (clone $tickets)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']),
                    'resolved_at'
                );
            }

            $technicianWorkload = User::query()
                ->where('is_active', true)
                ->whereHas('role', fn (Builder $query) => $query->where('name', 'mis_technician'))
                ->with('department:id,name')
                ->withCount('activeAssignments')
                ->orderBy('last_name')
                ->get();

            $departmentStats = Department::query()
                ->where('is_active', true)
                ->withCount([
                    'users as users_count' => fn (Builder $query) => $query->where('is_active', true),
                    'tickets',
                ])
                ->orderBy('name')
                ->get();
        } elseif ($isTechnician) {
            $dashboardRole = 'Technician workspace';
            $dashboardDescription = 'Your current assignments, service deadlines and completed work.';
            $activeTickets = Ticket::query()->whereHas('assignments', fn (Builder $query) => $query
                ->where('technician_id', $user->id)
                ->whereNull('ended_at'))
                ->whereNotIn('status', ['Resolved', 'Closed']);
            $metrics = [
                'Total tickets' => (clone $tickets)->count(),
                'Open' => (int) ($counts['Open'] ?? 0),
                'Assigned' => (int) ($counts['Assigned'] ?? 0),
                'In progress' => (int) ($counts['In Progress'] ?? 0),
                'Pending user' => (int) ($counts['Pending User'] ?? 0),
                'Resolved' => (int) ($counts['Resolved'] ?? 0),
                'Closed' => (int) ($counts['Closed'] ?? 0),
                'Reopened' => (int) ($counts['Reopened'] ?? 0),
                'My assigned tickets' => (clone $activeTickets)->count(),
                'New assignments' => TicketAssignment::query()
                    ->where('technician_id', $user->id)
                    ->whereNull('ended_at')
                    ->whereDate('assigned_at', today())
                    ->whereHas('ticket', fn (Builder $query) => $query->where('status', 'Assigned'))
                    ->count(),
                'In progress' => (clone $activeTickets)->where('status', 'In Progress')->count(),
                'Pending user' => (clone $activeTickets)->where('status', 'Pending User')->count(),
                'Critical' => (clone $activeTickets)
                    ->whereNotIn('status', ['Resolved', 'Closed'])
                    ->whereHas('priority', fn (Builder $query) => $query->where('name', 'Critical'))
                    ->count(),
                'Overdue' => $overdue,
                'Completed' => Ticket::query()
                    ->whereIn('status', ['Resolved', 'Closed'])
                    ->whereHas('assignments', fn (Builder $query) => $query->where('technician_id', $user->id))
                    ->count(),
            ];
        } elseif ($isRequester) {
            $dashboardRole = 'My service requests';
            $dashboardDescription = 'Track your requests and review recent service desk updates.';
            $metrics = [
                'My requests' => (clone $tickets)->count(),
                'Open requests' => (clone $tickets)->whereIn('status', ['Open', 'Reopened'])->count(),
                'In progress' => (clone $tickets)->whereIn('status', ['Assigned', 'In Progress'])->count(),
                'Pending user' => (int) ($counts['Pending User'] ?? 0),
                'Resolved' => (int) ($counts['Resolved'] ?? 0),
                'Closed' => (int) ($counts['Closed'] ?? 0),
            ];
        }

        return view('dashboard', compact(
            'metrics', 'recent', 'recentUpdates', 'monthly', 'technicianWorkload', 'departmentStats',
            'dashboardRole', 'dashboardDescription'
        ));
    }

    private function resolutionSlaCompliance(Builder $tickets): string
    {
        $resolved = $tickets->whereNotNull('resolved_at')->with('slaRecord')->get();
        $withSla = $resolved->filter(fn (Ticket $ticket) => $ticket->slaRecord?->resolution_due_at !== null);

        if ($withSla->isEmpty()) {
            return '—';
        }

        $met = $withSla->filter(fn (Ticket $ticket) => $ticket->resolved_at !== null && $ticket->resolved_at->lte($ticket->slaRecord->resolution_due_at)
        )->count();

        return round(($met / $withSla->count()) * 100).'%';
    }

    private function averageHours($tickets, string $timestamp): string
    {
        if ($tickets->isEmpty()) {
            return '—';
        }

        $minutes = $tickets->sum(fn (Ticket $ticket) => max(0, $ticket->created_at->diffInMinutes($ticket->{$timestamp}))
        ) / $tickets->count();

        return number_format($minutes / 60, 1);
    }
}
