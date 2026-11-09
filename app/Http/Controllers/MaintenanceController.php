<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $schedules = MaintenanceSchedule::with(['asset', 'technician', 'record'])->when($request->user()->isRole('mis_technician'), fn ($q) => $q->where('assigned_to', $request->user()->id))->when($request->status, fn ($q, $s) => $q->where('status', $s))->orderBy('scheduled_for')->paginate(20)->withQueryString();

        return view('maintenance.index', ['schedules' => $schedules, 'assets' => Asset::where('status', '!=', 'Retired')->orderBy('asset_tag')->get(), 'technicians' => User::where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'mis_technician'))->orderBy('last_name')->get()]);
    }

    public function store(Request $request, Audit $audit)
    {
        abort_unless($request->user()->isRole('system_administrator', 'mis_head'), 403);
        $data = $this->validateSchedule($request);
        $schedule = MaintenanceSchedule::create($data);
        $audit->record('maintenance.scheduled', $schedule);

        return back()->with('success', 'Maintenance scheduled.');
    }

    public function update(Request $request, MaintenanceSchedule $schedule, Audit $audit)
    {
        $manager = $request->user()->isRole('system_administrator', 'mis_head');
        abort_unless($manager || ($request->user()->isRole('mis_technician') && $schedule->assigned_to === $request->user()->id), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['Scheduled', 'In Progress', 'Completed', 'Cancelled'])], 'findings' => ['required_if:status,Completed', 'nullable', 'string', 'max:10000'], 'actions_performed' => ['required_if:status,Completed', 'nullable', 'string', 'max:10000'], 'next_maintenance_on' => ['nullable', 'date', 'after:today']]);
        $allowed = ['Scheduled' => ['In Progress', 'Completed', 'Cancelled'], 'In Progress' => ['Completed', 'Cancelled']];
        abort_unless(in_array($data['status'], $allowed[$schedule->status] ?? [], true), 422, 'That maintenance status transition is not allowed.');
        $schedule->update(['status' => $data['status']]);
        if ($data['status'] === 'Completed' && ! $schedule->record()->exists()) {
            MaintenanceRecord::create(['asset_id' => $schedule->asset_id, 'maintenance_schedule_id' => $schedule->id, 'technician_id' => $request->user()->id, 'performed_at' => now(), 'findings' => $data['findings'], 'actions_performed' => $data['actions_performed'], 'next_maintenance_on' => $data['next_maintenance_on'] ?? null, 'status' => 'Completed']);
        }
        $audit->record('maintenance.updated', $schedule, ['status' => $data['status']]);

        return back()->with('success', 'Maintenance record updated.');
    }

    private function validateSchedule(Request $request): array
    {
        $data = $request->validate(['asset_id' => ['required', 'exists:assets,id'], 'assigned_to' => ['required', 'exists:users,id'], 'title' => ['required', 'string', 'max:180'], 'instructions' => ['nullable', 'string', 'max:5000'], 'scheduled_for' => ['required', 'date', 'after_or_equal:today']]);
        Asset::whereKey($data['asset_id'])->where('status', '!=', 'Retired')->firstOrFail();
        User::whereKey($data['assigned_to'])->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'mis_technician'))->firstOrFail();

        return $data;
    }
}
