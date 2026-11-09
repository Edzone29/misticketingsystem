<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketSlaRecord;
use App\Models\TicketStatusHistory;
use App\Models\TicketSubcategory;
use App\Models\User;
use App\Services\Audit;
use App\Services\BusinessCalendar;
use App\Services\TicketNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    private const STATUSES = ['Open', 'Assigned', 'In Progress', 'Pending User', 'On Hold', 'Resolved', 'Closed', 'Reopened'];

    private function scope(Request $request)
    {
        $u = $request->user();
        $q = Ticket::query();
        if ($u->hasPermission('tickets.view_all')) {
            return $q;
        }
        if ($u->hasPermission('tickets.view_assigned')) {
            return $q->whereHas('assignments', fn ($a) => $a->where('technician_id', $u->id));
        }
        abort_unless($u->hasPermission('tickets.view_own'), 403);
        $q->where('requester_id', $u->id);

        return $q;
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ticket::class);
        $tickets = $this->scope($request)->with(['requester', 'department', 'category', 'priority', 'activeAssignment.technician'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($b) => $b->where('ticket_number', 'like', "%$s%")->orWhere('subject', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(15)->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function create()
    {
        Gate::authorize('create', Ticket::class);

        return view('tickets.create', ['categories' => TicketCategory::where('is_active', true)->orderBy('name')->get(), 'subcategories' => TicketSubcategory::where('is_active', true)->orderBy('name')->get(), 'assets' => Asset::query()->where('status', 'In Service')->orderBy('asset_tag')->limit(500)->get()]);
    }

    public function store(Request $request, BusinessCalendar $calendar, Audit $audit, TicketNotifications $notifications)
    {
        Gate::authorize('create', Ticket::class);
        $data = $request->validate(['ticket_category_id' => ['required', Rule::exists('ticket_categories', 'id')->where('is_active', true)], 'ticket_subcategory_id' => ['nullable', 'exists:ticket_subcategories,id'], 'asset_id' => ['nullable', 'exists:assets,id'], 'subject' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:10000'], 'building' => ['nullable', 'string', 'max:100'], 'room_number' => ['nullable', 'string', 'max:40'], 'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,txt,doc,docx,xls,xlsx', 'max:10240']]);
        if (! empty($data['ticket_subcategory_id'])) {
            abort_unless(TicketSubcategory::whereKey($data['ticket_subcategory_id'])->where('ticket_category_id', $data['ticket_category_id'])->where('is_active', true)->exists(), 422, 'Choose a subcategory in the selected category.');
        }
        $ticket = DB::transaction(function () use ($request, $data, $calendar) {
            $year = (int) now()->format('Y');
            DB::table('ticket_number_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $counter = DB::table('ticket_number_counters')->where('year', $year)->lockForUpdate()->first();
            $number = $counter->last_number + 1;
            DB::table('ticket_number_counters')->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);
            $priority = TicketPriority::where('name', 'Medium')->firstOrFail();
            $asset = isset($data['asset_id']) ? Asset::whereKey($data['asset_id'])->where('status', 'In Service')->firstOrFail() : null;
            $prefix = DB::table('system_settings')->where('key', 'ticket_prefix')->value('value') ?? 'MIS';
            $ticket = Ticket::create(['ticket_number' => sprintf('%s-%04d-%05d', $prefix, $year, $number), 'requester_id' => $request->user()->id, 'department_id' => $request->user()->department_id, 'ticket_category_id' => $data['ticket_category_id'], 'ticket_subcategory_id' => $data['ticket_subcategory_id'] ?? null, 'ticket_priority_id' => $priority->id, 'status' => 'Open', 'subject' => $data['subject'], 'description' => $data['description'], 'building' => $data['building'] ?? null, 'room_number' => $data['room_number'] ?? null, 'asset_number' => $asset?->asset_tag]);
            if ($asset) {
                $ticket->assets()->attach($asset->id);
            }
            TicketStatusHistory::create(['ticket_id' => $ticket->id, 'changed_by' => $request->user()->id, 'from_status' => null, 'to_status' => 'Open', 'reason' => 'Ticket submitted']);
            $policy = SlaPolicy::where('ticket_priority_id', $priority->id)->first();
            if (! $policy || $policy->enabled) {
                $response = $policy?->response_minutes ?? $priority->response_minutes;
                $resolution = $policy?->resolution_minutes ?? $priority->resolution_minutes;
                TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => $calendar->addWorkingMinutes(now()->toImmutable(), $response), 'resolution_due_at' => $calendar->addWorkingMinutes(now()->toImmutable(), $resolution)]);
            }

            return $ticket;
        });
        if ($request->hasFile('attachment')) {
            $this->saveAttachment($request, $ticket, $request->file('attachment'));
        }
        $audit->record('ticket.created', $ticket, ['ticket_number' => $ticket->ticket_number]);
        $notifications->newTicket($ticket);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket submitted. Reference: '.$ticket->ticket_number);
    }

    public function show(Request $request, Ticket $ticket, BusinessCalendar $calendar)
    {
        $this->authorizeTicket($request, $ticket);
        $ticket->load(['requester.department', 'department', 'category', 'subcategory', 'priority', 'activeAssignment.technician', 'assignments.technician', 'comments.user', 'statusHistory', 'slaRecord', 'attachments', 'assets']);
        $technicians = User::whereHas('role', fn ($q) => $q->where('name', 'mis_technician'))->where('is_active', true)->orderBy('last_name')->get();
        $priorities = TicketPriority::orderBy('sort_order')->get();
        $slaRecord = $ticket->slaRecord;
        $responseSla = $slaRecord ? $this->slaMessage($slaRecord->response_due_at, $slaRecord->response_met_at, $calendar, $slaRecord->paused_at) : 'Not tracked';
        $resolutionSla = $slaRecord ? $this->slaMessage($slaRecord->resolution_due_at, $slaRecord->resolution_met_at, $calendar, $slaRecord->paused_at) : 'Not tracked';
        $reopenDays = (int) (DB::table('system_settings')->where('key', 'reopen_days')->value('value') ?? 7);

        return view('tickets.show', compact('ticket', 'technicians', 'priorities', 'reopenDays', 'responseSla', 'resolutionSla'));
    }

    public function edit(Request $request, Ticket $ticket)
    {
        Gate::authorize('edit', $ticket);
        $categories = TicketCategory::where('is_active', true)->orderBy('name')->get();
        if ($ticket->category && ! $categories->contains('id', $ticket->ticket_category_id)) {
            $categories->push($ticket->category);
        }
        $subcategories = TicketSubcategory::where('is_active', true)->orderBy('name')->get();
        if ($ticket->subcategory && ! $subcategories->contains('id', $ticket->ticket_subcategory_id)) {
            $subcategories->push($ticket->subcategory);
        }

        return view('tickets.edit', compact('ticket', 'categories', 'subcategories'));
    }

    public function update(Request $request, Ticket $ticket, Audit $audit)
    {
        Gate::authorize('edit', $ticket);
        $data = $request->validate([
            'ticket_category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $ticket->ticket_category_id))],
            'ticket_subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'building' => ['nullable', 'string', 'max:100'],
            'room_number' => ['nullable', 'string', 'max:40'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,txt,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        if (! empty($data['ticket_subcategory_id'])) {
            $subcategoryMatches = TicketSubcategory::whereKey($data['ticket_subcategory_id'])
                ->where('ticket_category_id', $data['ticket_category_id'])
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $ticket->ticket_subcategory_id))
                ->exists();
            abort_unless($subcategoryMatches, 422, 'Choose a subcategory in the selected category.');
        }

        $fields = ['ticket_category_id', 'ticket_subcategory_id', 'subject', 'description', 'building', 'room_number'];
        $changedFields = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ((string) ($ticket->getAttribute($field) ?? '') !== (string) ($value ?? '')) {
                $changedFields[] = $field;
            }
        }
        if ($request->hasFile('attachment')) {
            $changedFields[] = 'attachment_added';
        }

        $ticket->update(collect($fields)->mapWithKeys(fn ($field) => [$field => $data[$field] ?? null])->all());
        if ($request->hasFile('attachment')) {
            $this->saveAttachment($request, $ticket, $request->file('attachment'));
        }
        if ($changedFields !== []) {
            $audit->record('ticket.updated', $ticket, ['changed_fields' => $changedFields]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket details updated.');
    }

    public function comment(Request $request, Ticket $ticket, Audit $audit, TicketNotifications $notifications)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'is_internal' => ['nullable', 'boolean'], 'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,txt,doc,docx,xls,xlsx', 'max:10240']]);
        Gate::authorize('comment', [$ticket, (bool) ($data['is_internal'] ?? false)]);
        $comment = $ticket->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_internal' => $data['is_internal'] ?? false]);
        if ($request->hasFile('attachment')) {
            $this->saveAttachment($request, $ticket, $request->file('attachment'), $comment->id);
        }
        if (! $comment->is_internal && ! $ticket->first_response_at && $request->user()->hasPermission('tickets.update')) {
            $ticket->update(['first_response_at' => now()]);
            $ticket->slaRecord?->update(['response_met_at' => now()]);
        }
        $audit->record('ticket.comment_added', $ticket, ['internal' => $comment->is_internal]);
        $notifications->commentAdded($ticket, $comment->id, $comment->is_internal);

        return back()->with('success', 'Reply added.');
    }

    public function assign(Request $request, Ticket $ticket, Audit $audit, TicketNotifications $notifications)
    {
        Gate::authorize('assign', $ticket);
        $data = $request->validate(['technician_id' => ['required', 'exists:users,id'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $tech = User::whereKey($data['technician_id'])->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'mis_technician'))->firstOrFail();
        abort_unless(in_array($ticket->status, ['Open', 'Reopened', 'Assigned', 'In Progress', 'Pending User', 'On Hold']), 422, 'A resolved or closed ticket cannot be assigned.');
        $assignment = DB::transaction(function () use ($request, $ticket, $tech, $data) {
            $ticket->assignments()->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
            $assignment = TicketAssignment::create(['ticket_id' => $ticket->id, 'technician_id' => $tech->id, 'assigned_by' => $request->user()->id, 'reason' => $data['reason'] ?? null, 'assigned_at' => now()]);
            if (in_array($ticket->status, ['Open', 'Reopened'])) {
                $this->changeStatus($request, $ticket, 'Assigned', 'Assignment created');
            }

            return $assignment;
        });
        $audit->record('ticket.assigned', $ticket, ['technician_id' => $tech->id]);
        $notifications->assigned($ticket, $tech, $assignment->id);

        return back()->with('success', 'Ticket assigned to '.$tech->name.'.');
    }

    public function priority(Request $request, Ticket $ticket, BusinessCalendar $calendar, Audit $audit)
    {
        Gate::authorize('prioritize', $ticket);
        $data = $request->validate(['ticket_priority_id' => ['required', 'exists:ticket_priorities,id'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $oldPriority = $ticket->priority->name;
        $priority = TicketPriority::findOrFail($data['ticket_priority_id']);
        $ticket->update(['ticket_priority_id' => $priority->id]);
        $policy = SlaPolicy::where('ticket_priority_id', $priority->id)->first();
        $sla = $ticket->slaRecord;
        if ($sla && (! $policy || $policy->enabled) && ! in_array($ticket->status, ['Resolved', 'Closed'])) {
            $sla->update(['response_due_at' => $ticket->first_response_at ? $sla->response_due_at : $calendar->addWorkingMinutes(now()->toImmutable(), $policy?->response_minutes ?? $priority->response_minutes), 'resolution_due_at' => $calendar->addWorkingMinutes(now()->toImmutable(), $policy?->resolution_minutes ?? $priority->resolution_minutes)]);
        }
        $audit->record('ticket.priority_changed', $ticket, ['from' => $oldPriority, 'to' => $priority->name, 'reason' => $data['reason']]);

        return back()->with('success', 'Priority updated with reason recorded.');
    }

    public function status(Request $request, Ticket $ticket, BusinessCalendar $calendar, Audit $audit, TicketNotifications $notifications)
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'reason' => ['nullable', 'string', 'max:1000'], 'resolution_details' => ['nullable', 'string', 'max:10000']]);
        $user = $request->user();
        $assignedTechnician = $user->hasPermission('tickets.update') && $ticket->assignments()->where('technician_id', $user->id)->whereNull('ended_at')->exists();
        Gate::authorize('updateStatus', [$ticket, $data['status']]);
        $staff = ($user->hasPermission('tickets.view_all') && $user->hasPermission('tickets.update')) || $assignedTechnician;
        $reopenDays = (int) (DB::table('system_settings')->where('key', 'reopen_days')->value('value') ?? 7);
        if (! $staff && $data['status'] === 'Reopened') {
            abort_if(! in_array($ticket->status, ['Resolved', 'Closed']) || ! $ticket->resolved_at || $ticket->resolved_at->lt(now()->subDays($reopenDays)), 422, 'The ticket is not eligible for reopening.');
        }
        $allowed = ['Open' => ['Assigned', 'In Progress', 'Pending User', 'On Hold'], 'Reopened' => ['Assigned', 'In Progress', 'Pending User', 'On Hold'], 'Assigned' => ['In Progress', 'Pending User', 'On Hold', 'Resolved'], 'In Progress' => ['Pending User', 'On Hold', 'Resolved'], 'Pending User' => ['In Progress', 'On Hold', 'Resolved'], 'On Hold' => ['In Progress', 'Pending User', 'Resolved'], 'Resolved' => ['Closed', 'Reopened'], 'Closed' => ['Reopened']];
        if ($staff) {
            abort_unless(in_array($data['status'], $allowed[$ticket->status] ?? [], true), 422, 'That status transition is not allowed.');
        }
        if ($data['status'] === 'Resolved') {
            abort_unless($staff, 403);
            $request->validate(['resolution_details' => ['required', 'string', 'min:10', 'max:10000']]);
        }
        if ($data['status'] === 'Closed') {
            abort_unless($ticket->status === 'Resolved', 422, 'Only resolved tickets can be closed.');
        }
        DB::transaction(function () use ($request, $ticket, $data, $calendar) {
            $sla = $ticket->slaRecord;
            if (! $ticket->first_response_at
                && in_array($data['status'], ['In Progress', 'Pending User', 'On Hold', 'Resolved'], true)) {
                $ticket->update(['first_response_at' => now()]);
                $sla?->update(['response_met_at' => now()]);
            }
            $pauseStatuses = explode(',', (string) (DB::table('system_settings')->where('key', 'sla_pause_statuses')->value('value') ?? 'Pending User,On Hold'));
            $wasPaused = in_array($ticket->status, $pauseStatuses, true);
            $willPause = in_array($data['status'], $pauseStatuses, true);
            if ($sla && ! $wasPaused && $willPause) {
                $sla->update(['paused_at' => now()]);
            }
            if ($sla && $wasPaused && ! $willPause && $sla->paused_at) {
                $pauseMinutes = $calendar->workingMinutesBetween($sla->paused_at->toImmutable(), now()->toImmutable());
                $updates = ['paused_at' => null, 'paused_minutes' => $sla->paused_minutes + max(0, $pauseMinutes)];
                if (! $sla->response_met_at) {
                    $updates['response_due_at'] = $calendar->addWorkingMinutes($sla->response_due_at->toImmutable(), max(0, $pauseMinutes));
                }
                if (! $sla->resolution_met_at) {
                    $updates['resolution_due_at'] = $calendar->addWorkingMinutes($sla->resolution_due_at->toImmutable(), max(0, $pauseMinutes));
                }
                $sla->update($updates);
            }
            if ($data['status'] === 'Reopened') {
                $ticket->update(['resolved_at' => null, 'closed_at' => null, 'resolution_details' => null]);
                if ($sla) {
                    $policy = SlaPolicy::where('ticket_priority_id', $ticket->ticket_priority_id)->first();
                    $resolutionMinutes = $policy?->enabled
                        ? $policy->resolution_minutes
                        : $ticket->priority->resolution_minutes;
                    $sla->update([
                        'resolution_due_at' => $calendar->addWorkingMinutes(now()->toImmutable(), $resolutionMinutes),
                        'resolution_met_at' => null,
                        'paused_at' => null,
                    ]);
                }
            }
            if ($data['status'] === 'Resolved') {
                $ticket->update(['resolution_details' => $data['resolution_details'], 'resolved_at' => now()]);
                $sla = $ticket->slaRecord;
                if ($sla) {
                    $sla->update(['resolution_met_at' => now()]);
                }
            }
            if ($data['status'] === 'Closed') {
                $ticket->update(['closed_at' => now()]);
            }
            $this->changeStatus($request, $ticket, $data['status'], $data['reason'] ?? null);
            if (in_array($data['status'], ['Resolved', 'Closed'], true)) {
                $ticket->assignments()->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
            }
        });
        $audit->record('ticket.status_changed', $ticket, ['status' => $data['status']]);
        $notifications->statusChanged($ticket, (int) $ticket->statusHistory()->latest('id')->value('id'));

        return back()->with('success', 'Ticket status updated.');
    }

    public function download(Request $request, Ticket $ticket, int $attachment)
    {
        $this->authorizeTicket($request, $ticket);
        $file = $ticket->attachments()->findOrFail($attachment);
        if ($file->ticketComment?->is_internal) {
            Gate::authorize('comment', [$ticket, true]);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function changeStatus(Request $request, Ticket $ticket, string $status, ?string $reason): void
    {
        $from = $ticket->status;
        if ($from === $status) {
            return;
        }
        $ticket->update(['status' => $status]);
        TicketStatusHistory::create(['ticket_id' => $ticket->id, 'changed_by' => $request->user()->id, 'from_status' => $from, 'to_status' => $status, 'reason' => $reason]);
    }

    private function saveAttachment(Request $request, Ticket $ticket, $file, ?int $commentId = null): void
    {
        $path = $file->store('tickets/'.$ticket->id, 'local');
        $ticket->attachments()->create(['uploaded_by' => $request->user()->id, 'ticket_comment_id' => $commentId, 'disk' => 'local', 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType() ?? 'application/octet-stream', 'size' => $file->getSize()]);
    }

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        Gate::authorize('view', $ticket);
    }

    private function slaMessage($dueAt, $metAt, BusinessCalendar $calendar, $pausedAt = null): string
    {
        $dueAt = $dueAt->toImmutable();
        if ($metAt) {
            $metAt = $metAt->toImmutable();
        }
        if (! $metAt && $pausedAt) {
            return 'Paused while awaiting action';
        }
        if ($metAt) {
            return $metAt->lte($dueAt) ? 'Met' : 'Met late';
        }
        $now = now()->toImmutable();
        $minutes = $dueAt->gte($now) ? $calendar->workingMinutesBetween($now, $dueAt) : $calendar->workingMinutesBetween($dueAt, $now);
        $hours = intdiv(abs($minutes), 60);
        $remaining = abs($minutes) % 60;
        $duration = $hours ? $hours.'h '.($remaining ? $remaining.'m' : '') : $remaining.'m';

        return $dueAt->lt($now) ? 'Overdue by '.$duration : $duration.' remaining';
    }
}
