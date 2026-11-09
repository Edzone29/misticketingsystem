<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\KnowledgeBaseArticle;
use App\Models\MaintenanceSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketSlaRecord;
use App\Models\TicketSubcategory;
use App\Models\User;
use App\Notifications\TicketEventNotification;
use App\Services\Audit;
use App\Services\BusinessCalendar;
use App\Services\SlaMonitor;
use App\Services\TicketNotifications;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HelpdeskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $head;

    private User $technician;

    private TicketCategory $category;

    private TicketPriority $priority;

    protected function setUp(): void
    {
        parent::setUp();
        $roles = [];
        foreach (['faculty_employee', 'mis_head', 'mis_technician', 'system_administrator'] as $name) {
            $roles[$name] = Role::create(['name' => $name, 'label' => ucwords(str_replace('_', ' ', $name))]);
        }
        $permissionNames = ['tickets.view_all', 'tickets.view_assigned', 'tickets.view_own', 'tickets.create', 'tickets.assign', 'tickets.prioritize', 'tickets.update', 'reports.view', 'maintenance.manage', 'maintenance.update', 'knowledge.manage', 'knowledge.view', 'feedback.submit'];
        $permissionIds = [];
        foreach ($permissionNames as $name) {
            $permissionIds[$name] = Permission::create(['name' => $name, 'label' => $name])->id;
        }
        $roles['system_administrator']->permissions()->sync(array_values($permissionIds));
        $roles['mis_head']->permissions()->sync(array_values(array_intersect_key($permissionIds, array_flip(['tickets.view_all', 'tickets.assign', 'tickets.prioritize', 'tickets.update', 'reports.view', 'maintenance.manage', 'knowledge.manage', 'knowledge.view']))));
        $roles['mis_technician']->permissions()->sync(array_values(array_intersect_key($permissionIds, array_flip(['tickets.view_assigned', 'tickets.update', 'maintenance.update', 'knowledge.view']))));
        $roles['faculty_employee']->permissions()->sync(array_values(array_intersect_key($permissionIds, array_flip(['tickets.view_own', 'tickets.create', 'knowledge.view', 'feedback.submit']))));
        $department = Department::create(['code' => 'TEST', 'name' => 'Test Department', 'is_active' => true]);
        $this->employee = $this->makeUser($roles['faculty_employee'], 'requester@test.invalid', $department);
        $this->head = $this->makeUser($roles['mis_head'], 'head@test.invalid', $department);
        $this->technician = $this->makeUser($roles['mis_technician'], 'tech@test.invalid', $department);
        $this->category = TicketCategory::create(['name' => 'Test Hardware', 'is_active' => true]);
        $this->priority = TicketPriority::create(['name' => 'Medium', 'response_minutes' => 240, 'resolution_minutes' => 960, 'sort_order' => 1]);
        SlaPolicy::create(['ticket_priority_id' => $this->priority->id, 'response_minutes' => 240, 'resolution_minutes' => 960, 'enabled' => true]);
    }

    public function test_requester_can_submit_a_ticket_with_unique_reference_and_history(): void
    {
        $subcategory = TicketSubcategory::create(['ticket_category_id' => $this->category->id, 'name' => 'Wi-Fi', 'is_active' => true]);
        $this->actingAs($this->employee)->post(route('tickets.store'), [
            'ticket_category_id' => $this->category->id, 'ticket_subcategory_id' => $subcategory->id, 'ticket_priority_id' => $this->priority->id,
            'subject' => 'Cannot connect to campus Wi-Fi', 'description' => 'The laptop disconnects after sign-in.',
        ])->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertMatchesRegularExpression('/^MIS-\d{4}-00001$/', $ticket->ticket_number);
        $this->assertSame('Open', $ticket->status);
        $this->assertSame($subcategory->id, $ticket->ticket_subcategory_id);
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'to_status' => 'Open']);
        $this->assertDatabaseHas('ticket_sla_records', ['ticket_id' => $ticket->id]);
        $this->assertSame(1, $this->head->notifications()->count());
        app(TicketNotifications::class)->newTicket($ticket);
        $this->assertSame(1, $this->head->notifications()->count(), 'Retrying the same ticket event must not duplicate a notification.');
    }

    public function test_ticket_number_counter_generates_sequential_unique_references(): void
    {
        foreach (['First request', 'Second request'] as $subject) {
            $this->actingAs($this->employee)->post(route('tickets.store'), [
                'ticket_category_id' => $this->category->id,
                'subject' => $subject,
                'description' => 'Testing sequential reference generation.',
            ])->assertRedirect();
        }

        $numbers = Ticket::orderBy('id')->pluck('ticket_number')->all();
        $this->assertCount(2, $numbers);
        $this->assertMatchesRegularExpression('/^MIS-\d{4}-00001$/', $numbers[0]);
        $this->assertSame(substr($numbers[0], 0, -5).'00002', $numbers[1]);
        $this->assertDatabaseHas('ticket_number_counters', ['year' => (int) now()->format('Y'), 'last_number' => 2]);
    }

    public function test_requester_cannot_view_another_users_ticket(): void
    {
        $ticket = $this->newTicket($this->head);
        $this->actingAs($this->employee)->get(route('tickets.show', $ticket))->assertForbidden();
    }

    public function test_institution_name_from_system_settings_is_used_in_page_branding(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'university_name'],
            ['value' => 'City of Ilagan College', 'created_at' => now(), 'updated_at' => now()],
        );

        $this->assertFileExists(public_path('images/city-of-ilagan-college-logo.png'));
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('City of Ilagan College')
            ->assertSee('city-of-ilagan-college-logo.png');
        $this->actingAs($this->employee)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('City of Ilagan College')
            ->assertSee('city-of-ilagan-college-logo.png');
    }

    public function test_requester_can_edit_own_unassigned_open_ticket_and_edit_is_audited(): void
    {
        $ticket = $this->newTicket($this->employee);

        $this->actingAs($this->employee)->get(route('tickets.edit', $ticket))
            ->assertOk()
            ->assertSee('Save ticket details');
        $this->put(route('tickets.update', $ticket), [
            'ticket_category_id' => $this->category->id,
            'subject' => 'Updated request subject',
            'description' => 'Additional details about the connection issue.',
            'building' => 'North Campus',
            'room_number' => '204',
        ])->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('Updated request subject', $ticket->subject);
        $this->assertSame('Additional details about the connection issue.', $ticket->description);
        $this->assertSame('North Campus', $ticket->building);
        $this->assertSame('204', $ticket->room_number);
        $this->assertSame('Open', $ticket->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.updated', 'subject_id' => $ticket->id]);

        $log = AuditLog::where('action', 'ticket.updated')->latest('id')->firstOrFail();
        $this->assertContains('subject', $log->metadata['changed_fields']);
        $this->assertArrayNotHasKey('description', $log->metadata);
    }

    public function test_ticket_edit_access_is_limited_to_requesters_and_current_mis_owners(): void
    {
        $otherTicket = $this->newTicket($this->head);
        $this->actingAs($this->employee)->get(route('tickets.edit', $otherTicket))->assertForbidden();

        $assignedRequesterTicket = $this->newTicket($this->employee);
        TicketAssignment::create([
            'ticket_id' => $assignedRequesterTicket->id,
            'technician_id' => $this->technician->id,
            'assigned_by' => $this->head->id,
            'assigned_at' => now(),
        ]);
        $this->get(route('tickets.edit', $assignedRequesterTicket))->assertForbidden();

        $headTicket = $this->newTicket($this->employee);
        $this->actingAs($this->head)->get(route('tickets.edit', $headTicket))->assertOk();

        $technicianTicket = $this->newTicket($this->employee);
        $this->actingAs($this->technician)->get(route('tickets.edit', $technicianTicket))->assertForbidden();
        TicketAssignment::create([
            'ticket_id' => $technicianTicket->id,
            'technician_id' => $this->technician->id,
            'assigned_by' => $this->head->id,
            'assigned_at' => now(),
        ]);
        $this->get(route('tickets.edit', $technicianTicket))->assertOk();

        $technicianTicket->update(['status' => 'Resolved']);
        $this->get(route('tickets.edit', $technicianTicket))->assertForbidden();
    }

    public function test_ticket_list_offers_view_and_edit_actions_according_to_access(): void
    {
        $ticket = $this->newTicket($this->employee);

        $this->actingAs($this->employee)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee(route('tickets.show', $ticket))
            ->assertSee(route('tickets.edit', $ticket));

        $ticket->update(['status' => 'Resolved']);
        $this->get(route('tickets.index'))
            ->assertOk()
            ->assertSee(route('tickets.show', $ticket))
            ->assertDontSee(route('tickets.edit', $ticket));
    }

    public function test_requester_can_close_and_reopen_a_recent_resolution_with_history(): void
    {
        $ticket = $this->newTicket($this->employee, 'Resolved');
        $ticket->update(['resolved_at' => now(), 'resolution_details' => 'The device was repaired and confirmed operational.']);
        $sla = TicketSlaRecord::create([
            'ticket_id' => $ticket->id,
            'response_due_at' => now()->subHour(),
            'response_met_at' => now()->subHours(2),
            'resolution_due_at' => now()->subMinutes(10),
            'resolution_met_at' => now(),
        ]);

        $this->actingAs($this->employee)->patch(route('tickets.status', $ticket), ['status' => 'Closed'])->assertRedirect();
        $this->assertSame('Closed', $ticket->fresh()->status);
        $this->patch(route('tickets.status', $ticket), ['status' => 'Reopened'])->assertRedirect();

        $this->assertSame('Reopened', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
        $this->assertNull($sla->fresh()->resolution_met_at);
        $this->assertTrue($sla->fresh()->resolution_due_at->isFuture());
        $this->assertNotNull($sla->fresh()->response_met_at);
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'from_status' => 'Resolved', 'to_status' => 'Closed']);
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'from_status' => 'Closed', 'to_status' => 'Reopened']);
    }

    public function test_only_active_ticket_categories_can_be_used_for_new_requests(): void
    {
        $inactive = TicketCategory::create(['name' => 'Retired Category', 'is_active' => false]);

        $this->actingAs($this->employee)->post(route('tickets.store'), [
            'ticket_category_id' => $inactive->id,
            'subject' => 'Request using a retired category',
            'description' => 'This must be rejected by server-side validation.',
        ])->assertSessionHasErrors('ticket_category_id');
        $this->assertSame(0, Ticket::count());
    }

    public function test_ticket_attachments_are_stored_on_the_private_disk(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('network-error.txt', 'Wireless adapter returned an error.');

        $this->actingAs($this->employee)->post(route('tickets.store'), [
            'ticket_category_id' => $this->category->id,
            'subject' => 'Network adapter error',
            'description' => 'The adapter stops after signing in.',
            'attachment' => $file,
        ])->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $attachment = $ticket->attachments()->firstOrFail();
        $this->assertSame('local', $attachment->disk);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('ticket_attachments', ['ticket_id' => $ticket->id, 'disk' => 'local']);
    }

    public function test_ticket_replies_are_saved_and_mis_response_stops_the_response_sla(): void
    {
        $ticket = $this->newTicket($this->employee);
        $sla = TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => now()->addHour(), 'resolution_due_at' => now()->addHours(4)]);
        TicketAssignment::create(['ticket_id' => $ticket->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()]);

        $this->actingAs($this->employee)->post(route('tickets.comments.store', $ticket), ['body' => 'The device light is blinking amber.'])
            ->assertRedirect();
        $requesterReply = $ticket->comments()->where('body', 'The device light is blinking amber.')->firstOrFail();
        $this->assertFalse($requesterReply->is_internal);
        $this->assertSame(1, $this->technician->notifications()->count());
        $this->assertNull($ticket->fresh()->first_response_at);

        $this->actingAs($this->head)->post(route('tickets.comments.store', $ticket), ['body' => 'MIS is checking the network adapter.'])
            ->assertRedirect();
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'user_id' => $this->head->id, 'body' => 'MIS is checking the network adapter.', 'is_internal' => false]);
        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->assertNotNull($sla->fresh()->response_met_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.comment_added']);
    }

    public function test_internal_note_and_attachment_are_saved_privately_and_hidden_from_requester(): void
    {
        Storage::fake('local');
        $ticket = $this->newTicket($this->employee);
        $sla = TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => now()->addHour(), 'resolution_due_at' => now()->addHours(4)]);
        $unassignedTechnician = $this->makeUser(Role::where('name', 'mis_technician')->firstOrFail(), 'unassigned-tech@test.invalid', $this->employee->department);
        TicketAssignment::create(['ticket_id' => $ticket->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()]);
        $upload = UploadedFile::fake()->create('diagnostics.txt', 1, 'text/plain');

        $this->actingAs($this->employee)->post(route('tickets.comments.store', $ticket), [
            'body' => 'Attempted private note',
            'is_internal' => '1',
        ])->assertForbidden();

        $this->actingAs($this->head)->post(route('tickets.comments.store', $ticket), [
            'body' => 'Internal note: check the managed switch port.',
            'is_internal' => '1',
            'attachment' => $upload,
        ])->assertRedirect();

        $comment = $ticket->comments()->where('is_internal', true)->firstOrFail();
        $attachment = $ticket->attachments()->where('ticket_comment_id', $comment->id)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('ticket_attachments', ['id' => $attachment->id, 'ticket_comment_id' => $comment->id, 'disk' => 'local']);
        $this->assertSame(1, $this->technician->notifications()->count());
        $this->assertSame(0, $unassignedTechnician->notifications()->count());
        $this->assertSame(0, $this->employee->notifications()->count());
        $this->assertNull($ticket->fresh()->first_response_at);
        $this->assertNull($sla->fresh()->response_met_at);

        $this->actingAs($this->employee)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('check the managed switch port');
        $this->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
        $this->actingAs($this->head)->get(route('tickets.show', $ticket))->assertOk()->assertSee('check the managed switch port');
        $this->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertOk();
    }

    public function test_supervisor_assignment_records_history_and_status(): void
    {
        $ticket = $this->newTicket($this->employee);
        $this->actingAs($this->head)->post(route('tickets.assign', $ticket), ['technician_id' => $this->technician->id, 'reason' => 'Network expertise'])->assertRedirect();
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->id, 'technician_id' => $this->technician->id, 'ended_at' => null]);
        $this->assertSame('Assigned', $ticket->fresh()->status);
    }

    public function test_reassignment_closes_the_previous_assignment_and_keeps_history(): void
    {
        $ticket = $this->newTicket($this->employee);
        $secondTechnician = $this->makeUser(Role::where('name', 'mis_technician')->firstOrFail(), 'second-tech@test.invalid', $this->employee->department);

        $this->actingAs($this->head)->post(route('tickets.assign', $ticket), ['technician_id' => $this->technician->id, 'reason' => 'Initial assignment'])->assertRedirect();
        $previous = $ticket->assignments()->firstOrFail();
        $this->post(route('tickets.assign', $ticket), ['technician_id' => $secondTechnician->id, 'reason' => 'Reassigned for availability'])->assertRedirect();

        $previous->refresh();
        $this->assertNotNull($previous->ended_at);
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->id, 'technician_id' => $secondTechnician->id, 'ended_at' => null]);
        $this->assertSame(2, $ticket->assignments()->count());
        $this->assertSame(1, $ticket->assignments()->whereNull('ended_at')->count());
    }

    public function test_resolution_requires_details_and_records_status_history(): void
    {
        $ticket = $this->newTicket($this->employee, 'In Progress');
        TicketAssignment::create(['ticket_id' => $ticket->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()]);
        $this->actingAs($this->technician)->patch(route('tickets.status', $ticket), ['status' => 'Resolved'])->assertSessionHasErrors('resolution_details');
        $this->assertSame('In Progress', $ticket->fresh()->status);
        $this->actingAs($this->technician)->patch(route('tickets.status', $ticket), ['status' => 'Resolved', 'resolution_details' => 'Reinstalled the wireless adapter and confirmed connectivity.'])->assertRedirect();
        $this->assertSame('Resolved', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'from_status' => 'In Progress', 'to_status' => 'Resolved']);
    }

    public function test_staff_progress_update_records_the_first_response_sla(): void
    {
        $ticket = $this->newTicket($this->employee, 'Assigned');
        TicketAssignment::create(['ticket_id' => $ticket->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()]);
        $sla = TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => now()->addHour(), 'resolution_due_at' => now()->addHours(4)]);

        $this->actingAs($this->technician)->patch(route('tickets.status', $ticket), ['status' => 'In Progress'])->assertRedirect();

        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->assertNotNull($sla->fresh()->response_met_at);
    }

    public function test_unassigned_technician_cannot_change_ticket_status(): void
    {
        $ticket = $this->newTicket($this->employee);
        $this->actingAs($this->technician)->patch(route('tickets.status', $ticket), ['status' => 'In Progress'])->assertForbidden();
    }

    public function test_only_supervisor_can_change_priority_and_reason_is_audited(): void
    {
        $ticket = $this->newTicket($this->employee);
        $high = TicketPriority::create(['name' => 'High', 'response_minutes' => 30, 'resolution_minutes' => 480, 'sort_order' => 2]);
        $this->actingAs($this->employee)->put(route('tickets.priority', $ticket), ['ticket_priority_id' => $high->id, 'reason' => 'Urgent'])->assertForbidden();
        $this->actingAs($this->head)->put(route('tickets.priority', $ticket), ['ticket_priority_id' => $high->id, 'reason' => 'Several staff are affected'])->assertRedirect();
        $this->assertSame('High', $ticket->fresh()->priority->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.priority_changed']);
    }

    public function test_sla_minutes_skip_non_working_time(): void
    {
        $due = app(BusinessCalendar::class)->addWorkingMinutes(CarbonImmutable::parse('2026-11-06 16:30:00', 'Asia/Manila'), 60);
        $this->assertSame('2026-11-09 08:30:00', $due->format('Y-m-d H:i:s'));
        $remaining = app(BusinessCalendar::class)->workingMinutesBetween(CarbonImmutable::parse('2026-11-06 16:30:00', 'Asia/Manila'), CarbonImmutable::parse('2026-11-09 08:30:00', 'Asia/Manila'));
        $this->assertSame(60, $remaining);
        DB::table('holidays')->insert(['date' => '2026-11-09', 'name' => 'Test closure', 'is_working_day' => false, 'created_at' => now(), 'updated_at' => now()]);
        $due = app(BusinessCalendar::class)->addWorkingMinutes(CarbonImmutable::parse('2026-11-06 16:30:00', 'Asia/Manila'), 60);
        $this->assertSame('2026-11-10 08:30:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_ticket_detail_hides_internal_notes_from_requester(): void
    {
        $ticket = $this->newTicket($this->employee);
        TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $this->head->id, 'body' => 'Staff-only diagnostic detail.', 'is_internal' => true]);
        $this->actingAs($this->employee)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('Staff-only diagnostic detail.');
        $this->actingAs($this->head)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Staff-only diagnostic detail.');
    }

    public function test_ticket_detail_shows_business_time_sla_state(): void
    {
        $ticket = $this->newTicket($this->employee);
        $calendar = app(BusinessCalendar::class);
        $now = now()->toImmutable();
        TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => $calendar->addWorkingMinutes($now, 60), 'resolution_due_at' => $calendar->addWorkingMinutes($now, 240)]);
        $this->actingAs($this->employee)->get(route('tickets.show', $ticket))->assertOk()->assertSee('First response SLA')->assertSee('Resolution SLA')->assertSee('remaining');
    }

    public function test_dashboard_and_knowledge_base_render_from_database(): void
    {
        $this->actingAs($this->employee)->get(route('dashboard'))->assertOk()->assertSee('Recent tickets');
        $this->actingAs($this->employee)->get(route('knowledge.index'))->assertOk()->assertSee('Self-service guides');
    }

    public function test_admin_can_register_asset_and_requester_can_rate_completed_ticket(): void
    {
        $assetCategory = AssetCategory::create(['name' => 'Test computers', 'is_active' => true]);
        $assetData = ['asset_tag' => 'TEST-001', 'name' => 'Office workstation', 'asset_category_id' => $assetCategory->id, 'department_id' => $this->employee->department_id, 'assigned_user_id' => $this->employee->id, 'condition' => 'Good', 'status' => 'In Service'];
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'admin@test.invalid', $this->employee->department);
        $this->actingAs($admin)->post(route('assets.store'), $assetData)->assertRedirect();
        $this->assertDatabaseHas('assets', ['asset_tag' => 'TEST-001', 'name' => 'Office workstation']);
        $asset = Asset::where('asset_tag', 'TEST-001')->firstOrFail();
        $this->put(route('assets.update', $asset), [...$assetData, 'name' => 'Faculty office workstation', 'condition' => 'Fair'])->assertRedirect();
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Faculty office workstation', 'condition' => 'Fair', 'assigned_user_id' => $this->employee->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.updated']);
        $ticket = $this->newTicket($this->employee, 'Resolved');
        $this->actingAs($this->employee)->post(route('tickets.feedback', $ticket), ['rating' => 5, 'comments' => 'Resolved promptly.'])->assertRedirect();
        $this->assertDatabaseHas('feedback', ['ticket_id' => $ticket->id, 'rating' => 5]);
    }

    public function test_feedback_permission_is_enforced_on_the_server(): void
    {
        $ticket = $this->newTicket($this->employee, 'Resolved');
        $ticket->update(['resolved_at' => now()]);
        $this->employee->role->permissions()->detach(Permission::where('name', 'feedback.submit')->value('id'));

        $this->actingAs($this->employee)->post(route('tickets.feedback', $ticket), ['rating' => 5])->assertForbidden();
        $this->assertDatabaseMissing('feedback', ['ticket_id' => $ticket->id]);
    }

    public function test_asset_edit_preserves_inactive_existing_relationships(): void
    {
        $category = AssetCategory::create(['name' => 'Inactive equipment group', 'is_active' => true]);
        $department = Department::create(['code' => 'ASSET-OLD', 'name' => 'Legacy Asset Department', 'is_active' => true]);
        $assignedUser = $this->makeUser(Role::where('name', 'faculty_employee')->firstOrFail(), 'legacy-asset-user@test.invalid', $department);
        $asset = Asset::create([
            'asset_tag' => 'LEGACY-001', 'name' => 'Legacy workstation', 'asset_category_id' => $category->id,
            'department_id' => $department->id, 'assigned_user_id' => $assignedUser->id,
            'condition' => 'Good', 'status' => 'In Service',
        ]);
        $category->update(['is_active' => false]);
        $department->update(['is_active' => false]);
        $assignedUser->update(['is_active' => false]);
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'legacy-asset-admin@test.invalid', $this->employee->department);

        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Inactive equipment group (Inactive)')
            ->assertSee('Legacy Asset Department (Inactive)')
            ->assertSee('Test User (Inactive)');

        $this->post(route('assets.store'), [
            'asset_tag' => 'NEW-INACTIVE-REF', 'name' => 'Invalid new asset', 'asset_category_id' => $category->id,
            'department_id' => $department->id, 'assigned_user_id' => $assignedUser->id,
            'condition' => 'Good', 'status' => 'In Service',
        ])->assertSessionHasErrors(['asset_category_id', 'department_id', 'assigned_user_id']);

        $this->put(route('assets.update', $asset), [
            'asset_tag' => 'LEGACY-001', 'name' => 'Legacy workstation updated', 'asset_category_id' => $category->id,
            'department_id' => $department->id, 'assigned_user_id' => $assignedUser->id,
            'condition' => 'Good', 'status' => 'In Service',
        ])->assertRedirect();
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'asset_category_id' => $category->id,
            'department_id' => $department->id,
            'assigned_user_id' => $assignedUser->id,
            'name' => 'Legacy workstation updated',
        ]);
    }

    public function test_staff_can_view_database_report_and_export_csv(): void
    {
        $ticket = $this->newTicket($this->employee);
        $this->actingAs($this->technician)->get(route('reports.tickets'))->assertForbidden();
        $this->actingAs($this->head)->get(route('reports.tickets'))->assertOk()->assertSee($ticket->ticket_number)->assertSee('SLA compliance')->assertSee('By Department')->assertSee('User satisfaction');
        $response = $this->get(route('reports.tickets.export', ['status' => 'Open']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($ticket->ticket_number, $response->streamedContent());
    }

    public function test_report_sla_compliance_uses_actual_met_overdue_and_paused_targets(): void
    {
        $metTicket = $this->newTicket($this->employee, 'Resolved');
        $metTicket->update(['first_response_at' => now()->subMinutes(30), 'resolved_at' => now()->subMinutes(15)]);
        TicketAssignment::create([
            'ticket_id' => $metTicket->id,
            'technician_id' => $this->technician->id,
            'assigned_by' => $this->head->id,
            'assigned_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
        TicketSlaRecord::create([
            'ticket_id' => $metTicket->id,
            'response_due_at' => now()->addHour(),
            'response_met_at' => now()->subMinutes(30),
            'resolution_due_at' => now()->addHours(2),
            'resolution_met_at' => now()->subMinutes(15),
        ]);

        $overdueTicket = $this->newTicket($this->employee, 'In Progress');
        TicketSlaRecord::create([
            'ticket_id' => $overdueTicket->id,
            'response_due_at' => now()->subMinutes(5),
            'resolution_due_at' => now()->subMinutes(5),
        ]);

        $pausedTicket = $this->newTicket($this->employee, 'Pending User');
        TicketSlaRecord::create([
            'ticket_id' => $pausedTicket->id,
            'response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->subHour(),
            'paused_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($this->head)->get(route('reports.tickets'))
            ->assertOk()
            ->assertViewHas('sla', fn ($sla) => (int) $sla->response_evaluated === 2
                && (int) $sla->response_met === 1
                && (int) $sla->resolution_evaluated === 2
                && (int) $sla->resolution_met === 1)
            ->assertViewHas('technicianSummary', fn ($summary) => $summary->firstWhere('name', $this->technician->name)['assigned'] === 1
                && $summary->firstWhere('name', $this->technician->name)['completed'] === 1);
    }

    public function test_audit_log_is_filterable_and_only_administrators_can_read_it(): void
    {
        $ticket = $this->newTicket($this->employee);
        $this->actingAs($this->head);
        app(Audit::class)->record('ticket.test_report_action', $ticket, ['reason' => 'Audit report test']);
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'audit-report-admin@test.invalid', $this->employee->department);

        $this->actingAs($this->employee)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('audit.index', [
            'action' => 'ticket.test_report_action',
            'user_id' => $this->head->id,
        ]))->assertOk()->assertSee('ticket.test_report_action')->assertSee('Audit report test');
        $this->get(route('audit.index', ['action' => 'nonexistent.event']))
            ->assertOk()
            ->assertSee('No audit records match these filters.');
    }

    public function test_staff_can_export_filtered_ticket_reports_as_pdf_and_excel(): void
    {
        $this->newTicket($this->employee);
        $pdf = $this->actingAs($this->head)->get(route('reports.tickets.export.pdf'));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $excel = $this->get(route('reports.tickets.export.xlsx'));
        $excel->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $excel->headers->get('content-type'));
    }

    public function test_asset_and_maintenance_reports_apply_filters_and_support_download_formats(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'asset-report-admin@test.invalid', $this->employee->department);
        $category = AssetCategory::create(['name' => 'Report equipment', 'is_active' => true]);
        $inService = Asset::create(['asset_tag' => 'RPT-001', 'name' => 'Report workstation', 'asset_category_id' => $category->id, 'department_id' => $this->employee->department_id, 'condition' => 'Good', 'status' => 'In Service']);
        Asset::create(['asset_tag' => 'RPT-002', 'name' => 'Storage workstation', 'asset_category_id' => $category->id, 'department_id' => $this->employee->department_id, 'condition' => 'Fair', 'status' => 'In Storage']);
        $schedule = MaintenanceSchedule::create(['asset_id' => $inService->id, 'assigned_to' => $this->technician->id, 'title' => 'Inspect report workstation', 'scheduled_for' => today()->addDay(), 'status' => 'Scheduled']);
        MaintenanceSchedule::create(['asset_id' => $inService->id, 'assigned_to' => $this->head->id, 'title' => 'Unrelated maintenance', 'scheduled_for' => today()->addDay(), 'status' => 'Scheduled']);

        $this->actingAs($admin)->get(route('reports.assets', ['status' => 'In Service']))
            ->assertOk()->assertSee('RPT-001')->assertDontSee('RPT-002');
        $assetCsv = $this->get(route('reports.assets.csv', ['status' => 'In Service']))->assertOk();
        $this->assertStringContainsString('RPT-001', $assetCsv->streamedContent());
        $this->assertStringNotContainsString('RPT-002', $assetCsv->streamedContent());
        $this->get(route('reports.assets.excel', ['status' => 'In Service']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $assetPdf = $this->get(route('reports.assets.pdf', ['status' => 'In Service']))->assertOk();
        $this->assertStringStartsWith('%PDF', $assetPdf->getContent());

        $this->get(route('reports.maintenance', ['technician_id' => $this->technician->id]))
            ->assertOk()->assertSee($schedule->title)->assertDontSee('Unrelated maintenance');
        $maintenanceCsv = $this->get(route('reports.maintenance.csv', ['technician_id' => $this->technician->id]))->assertOk();
        $this->assertStringContainsString($schedule->title, $maintenanceCsv->streamedContent());
        $this->assertStringNotContainsString('Unrelated maintenance', $maintenanceCsv->streamedContent());
        $this->get(route('reports.maintenance.excel', ['technician_id' => $this->technician->id]))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $maintenancePdf = $this->get(route('reports.maintenance.pdf', ['technician_id' => $this->technician->id]))->assertOk();
        $this->assertStringStartsWith('%PDF', $maintenancePdf->getContent());
    }

    public function test_knowledge_article_is_hidden_until_published(): void
    {
        $article = KnowledgeBaseArticle::create(['author_id' => $this->head->id, 'title' => 'Reset university password', 'slug' => 'reset-university-password', 'category' => 'Accounts', 'body' => 'Use the official self-service reset process.', 'is_published' => false]);
        $this->actingAs($this->employee)->get(route('knowledge.show', $article))->assertNotFound();
        $article->update(['is_published' => true, 'published_at' => now()]);
        $this->get(route('knowledge.show', $article))->assertOk()->assertSee('Use the official self-service reset process.');
    }

    public function test_admin_settings_control_ticket_prefix_and_work_calendar(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'admin-settings@test.invalid', $this->employee->department);
        $this->actingAs($this->head)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Service level targets');
        $this->put(route('settings.update'), ['university_name' => 'Test University', 'system_name' => 'Test Helpdesk', 'contact_email' => 'mis@test.invalid', 'ticket_prefix' => 'TST', 'working_days' => ['1', '2', '3', '4', '5'], 'working_start' => '09:00', 'working_end' => '18:00', 'reopen_days' => 5, 'sla_pause_statuses' => ['Pending User'], 'sla_reminder_minutes' => 45, 'sla_escalation_minutes' => 90, 'email_notifications' => 0])->assertRedirect();
        $this->assertDatabaseHas('system_settings', ['key' => 'ticket_prefix', 'value' => 'TST']);
    }

    public function test_preventive_maintenance_assignment_and_completion_are_recorded(): void
    {
        $assetCategory = AssetCategory::create(['name' => 'Maintenance computers', 'is_active' => true]);
        $asset = Asset::create(['asset_tag' => 'MAINT-001', 'name' => 'Network workstation', 'asset_category_id' => $assetCategory->id, 'condition' => 'Good', 'status' => 'In Service']);
        $this->actingAs($this->head)->post(route('maintenance.store'), ['asset_id' => $asset->id, 'assigned_to' => $this->technician->id, 'title' => 'Quarterly inspection', 'instructions' => 'Inspect cabling and clean intake.', 'scheduled_for' => today()->addWeek()->toDateString()])->assertRedirect();
        $schedule = MaintenanceSchedule::firstOrFail();
        $this->actingAs($this->technician)->patch(route('maintenance.update', $schedule), ['status' => 'Completed', 'findings' => 'Dust buildup on ventilation intake.', 'actions_performed' => 'Cleaned intake and verified temperature.', 'next_maintenance_on' => today()->addMonths(3)->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('maintenance_records', ['maintenance_schedule_id' => $schedule->id, 'technician_id' => $this->technician->id, 'status' => 'Completed']);
        $this->patch(route('maintenance.update', $schedule), ['status' => 'Scheduled'])->assertStatus(422);
    }

    public function test_active_user_can_sign_in_and_inactive_user_cannot(): void
    {
        $this->post(route('login.store'), ['email' => $this->employee->email, 'password' => 'test-password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->employee);
        auth()->logout();
        $this->employee->update(['is_active' => false]);
        $this->post(route('login.store'), ['email' => $this->employee->email, 'password' => 'test-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.failed']);
    }

    public function test_administrator_user_directory_renders(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'directory-admin@test.invalid', $this->employee->department);
        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('User accounts');
    }

    public function test_administrator_can_manage_ticket_categories(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'categories-admin@test.invalid', $this->employee->department);
        $this->actingAs($this->employee)->get(route('categories.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('categories.index'))->assertOk()->assertSee('Configured categories');
        $this->post(route('categories.store'), ['name' => 'Account access', 'description' => 'University account requests.'])->assertRedirect();
        $category = TicketCategory::where('name', 'Account access')->firstOrFail();
        $this->post(route('subcategories.store'), ['ticket_category_id' => $category->id, 'name' => 'Password reset'])->assertRedirect();
        $this->assertDatabaseHas('ticket_subcategories', ['ticket_category_id' => $category->id, 'name' => 'Password reset', 'is_active' => true]);
    }

    public function test_role_permissions_are_editable_and_enforced_on_routes(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'roles-admin@test.invalid', $this->employee->department);
        $this->actingAs($admin)->get(route('roles.index'))->assertOk()->assertSee('Role permission matrix');
        $permission = Permission::where('name', 'tickets.create')->firstOrFail();
        $this->actingAs($this->employee)->get(route('tickets.create'))->assertOk();
        $this->employee->role->permissions()->detach($permission->id);
        $this->get(route('tickets.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('roles.store'), ['name' => 'department_coordinator', 'label' => 'Department Coordinator'])->assertRedirect();
        $role = Role::where('name', 'department_coordinator')->firstOrFail();
        $this->put(route('roles.permissions.update', $role), ['permission_ids' => [$permission->id]])->assertRedirect();
        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());
    }

    public function test_administrator_can_manage_asset_categories_and_view_asset_history(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'asset-admin@test.invalid', $this->employee->department);
        $category = AssetCategory::create(['name' => 'Workstations', 'is_active' => true]);
        $asset = Asset::create(['asset_tag' => 'WS-001', 'name' => 'Faculty workstation', 'asset_category_id' => $category->id, 'condition' => 'Good', 'status' => 'In Service']);
        $this->actingAs($admin)->get(route('asset-categories.index'))->assertOk()->assertSee('Inventory categories');
        $this->post(route('asset-categories.store'), ['name' => 'Network equipment'])->assertRedirect();
        $this->put(route('asset-categories.update', $category), ['name' => 'Desktop computers', 'is_active' => 0])->assertRedirect();
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Maintenance history')->assertSee('Linked service tickets');
        $this->assertDatabaseHas('asset_categories', ['id' => $category->id, 'name' => 'Desktop computers', 'is_active' => false]);
    }

    public function test_password_reset_link_can_be_requested_and_used_once(): void
    {
        Notification::fake();
        $this->get(route('password.request'))->assertOk()->assertSee('Reset password');
        $this->post(route('password.email'), ['email' => $this->employee->email])->assertSessionHas('status');
        Notification::assertSentTo($this->employee, ResetPassword::class);
        $token = app('auth.password.broker')->createToken($this->employee);
        $this->get(route('password.reset', ['token' => $token, 'email' => $this->employee->email]))->assertOk()->assertSee('Choose a new password');
        $this->post(route('password.update'), ['token' => $token, 'email' => $this->employee->email, 'password' => 'NewStrong!Pass2026', 'password_confirmation' => 'NewStrong!Pass2026'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewStrong!Pass2026', $this->employee->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'subject_id' => $this->employee->id]);
    }

    public function test_administrator_can_import_csv_accounts_and_send_setup_links(): void
    {
        Notification::fake();
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'import-admin@test.invalid', $this->employee->department);
        $csv = "employee_number,first_name,middle_name,last_name,email,contact_number,position,department_code,role\r\nEMP-CSV-7,Jamie,,Santos,jamie.santos@test.invalid,09170000000,Faculty,TEST,faculty_employee\r\n";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $this->actingAs($admin)->post(route('users.import'), ['csv' => $file])->assertRedirect()->assertSessionHas('success');
        $imported = User::where('email', 'jamie.santos@test.invalid')->firstOrFail();
        $this->assertSame('EMP-CSV-7', $imported->employee_number);
        $this->assertNotSame('DemoOnly!2026', $imported->password);
        Notification::assertSentTo($imported, ResetPassword::class);
        $this->actingAs($this->employee)->post(route('users.import'), ['csv' => $file])->assertForbidden();
    }

    public function test_sla_clock_pauses_and_extends_unmet_resolution_deadline_on_resume(): void
    {
        $ticket = $this->newTicket($this->employee, 'In Progress');
        TicketAssignment::create(['ticket_id' => $ticket->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()]);
        $sla = TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => '2026-11-09 09:00:00', 'resolution_due_at' => '2026-11-10 12:00:00']);
        Carbon::setTestNow('2026-11-06 16:30:00');
        $this->actingAs($this->technician)->patch(route('tickets.status', $ticket), ['status' => 'Pending User'])->assertRedirect();
        $this->assertNotNull($sla->fresh()->paused_at);
        $this->get(route('tickets.show', $ticket))->assertSee('Paused while awaiting action');
        Carbon::setTestNow('2026-11-09 09:00:00');
        $this->patch(route('tickets.status', $ticket), ['status' => 'In Progress'])->assertRedirect();
        $sla = $sla->fresh();
        $this->assertNull($sla->paused_at);
        $this->assertSame(90, $sla->paused_minutes);
        $this->assertNotNull($sla->response_met_at);
        $this->assertSame('2026-11-09 09:00:00', $sla->response_due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 13:30:00', $sla->resolution_due_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_sla_monitor_sends_an_idempotent_warning_and_overdue_escalation(): void
    {
        $ticket = $this->newTicket($this->employee, 'In Progress');
        $this->actingAs($this->head)->post(route('tickets.assign', $ticket), ['technician_id' => $this->technician->id])->assertRedirect();
        $sla = TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => now()->addMinutes(15), 'resolution_due_at' => now()->addMinutes(15), 'response_met_at' => now()]);
        $monitor = app(SlaMonitor::class);
        $monitor->run();
        $monitor->run();
        $this->assertSame(1, $this->employee->notifications()->where('type', TicketEventNotification::class)->whereRaw("JSON_EXTRACT(data,'$.event') = 'sla.warning'")->count());
        $sla->update(['resolution_due_at' => now()->subMinutes(90)]);
        $monitor->run();
        $monitor->run();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->head->id, 'type' => TicketEventNotification::class]);
        $this->assertSame(1, $this->head->notifications()->whereRaw("JSON_EXTRACT(data,'$.event') = 'sla.escalated'")->count());
    }

    public function test_pending_user_reminder_is_sent_even_when_the_sla_clock_is_paused(): void
    {
        $ticket = $this->newTicket($this->employee, 'Pending User');
        DB::table('ticket_status_history')->insert(['ticket_id' => $ticket->id, 'changed_by' => $this->head->id, 'from_status' => 'In Progress', 'to_status' => 'Pending User', 'reason' => 'Awaiting information', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        TicketSlaRecord::create(['ticket_id' => $ticket->id, 'response_due_at' => now()->subDay(), 'resolution_due_at' => now()->addDay(), 'paused_at' => now()->subDay()]);
        $monitor = app(SlaMonitor::class);
        $monitor->run();
        $monitor->run();
        $this->assertSame(1, $this->employee->notifications()->whereRaw("JSON_EXTRACT(data,'$.event') = 'ticket.pending_reminder'")->count());
    }

    public function test_employee_dashboard_only_contains_the_requesters_database_records(): void
    {
        $ownTicket = $this->newTicket($this->employee);
        $otherUser = $this->makeUser(Role::where('name', 'faculty_employee')->firstOrFail(), 'other-requester@test.invalid', $this->employee->department);
        $otherTicket = $this->newTicket($otherUser);

        $this->actingAs($this->employee)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['My requests'] === 1)
            ->assertSee('My requests')
            ->assertSee($ownTicket->ticket_number)
            ->assertDontSee($otherTicket->ticket_number);
    }

    public function test_technician_dashboard_uses_current_and_historical_assignments(): void
    {
        $active = $this->newTicket($this->employee, 'In Progress');
        TicketAssignment::create(['ticket_id' => $active->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()]);
        $completed = $this->newTicket($this->employee, 'Resolved');
        TicketAssignment::create(['ticket_id' => $completed->id, 'technician_id' => $this->technician->id, 'assigned_by' => $this->head->id, 'assigned_at' => now()->subDay(), 'ended_at' => now()]);

        $this->actingAs($this->technician)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['Total tickets'] === 2
                && $metrics['My assigned tickets'] === 1
                && $metrics['In progress'] === 1
                && $metrics['Completed'] === 1)
            ->assertSee('My assigned tickets')
            ->assertSee('Completed')
            ->assertSee($active->ticket_number)
            ->assertSee($completed->ticket_number);
    }

    public function test_administrator_and_mis_head_dashboards_show_operational_metrics(): void
    {
        $this->newTicket($this->employee);
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'dashboard-admin@test.invalid', $this->employee->department);
        $inactiveUser = $this->makeUser(Role::where('name', 'faculty_employee')->firstOrFail(), 'inactive-dashboard@test.invalid', $this->employee->department);
        $inactiveUser->update(['is_active' => false]);
        Department::create(['code' => 'INACTIVE', 'name' => 'Inactive Department', 'is_active' => false]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['Total tickets'] === 1
                && $metrics['Open'] === 1
                && $metrics['Total users'] === 5
                && $metrics['Total departments'] === 2);

        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['Total tickets'] === 1
                && $metrics['Open'] === 1
                && $metrics['Unassigned'] === 1
                && array_key_exists('SLA compliance', $metrics));
    }

    public function test_login_rejects_inactive_accounts_and_admin_routes_are_role_protected(): void
    {
        $inactive = $this->makeUser(Role::where('name', 'faculty_employee')->firstOrFail(), 'inactive@test.invalid', $this->employee->department);
        $inactive->update(['is_active' => false]);

        $this->post(route('login.store'), ['email' => $inactive->email, 'password' => 'test-password'])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->employee)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->employee)->get(route('departments.index'))->assertForbidden();
        $this->post(route('users.store'), [])->assertForbidden();
        $this->post(route('departments.store'), [])->assertForbidden();

        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'access-admin@test.invalid', $this->employee->department);
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($admin)->get(route('departments.index'))->assertOk();
    }

    public function test_login_attempts_are_rate_limited_and_failures_are_audited(): void
    {
        $email = 'rate-limit@test.invalid';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertSame(5, RateLimiter::attempts(strtolower($email).'|127.0.0.1'));
        $this->assertSame(5, AuditLog::where('action', 'login.failed')->count());
    }

    public function test_view_all_permission_without_ticket_update_cannot_change_ticket_state(): void
    {
        $readOnlyRole = Role::create(['name' => 'read_only_auditor', 'label' => 'Read-only Auditor']);
        $readOnlyRole->permissions()->sync([Permission::where('name', 'tickets.view_all')->value('id')]);
        $viewer = $this->makeUser($readOnlyRole, 'viewer@test.invalid', $this->employee->department);
        $ticket = $this->newTicket($this->employee, 'Resolved');
        $ticket->update(['resolved_at' => now(), 'resolution_details' => 'The request was completed and verified.']);

        $this->actingAs($viewer)->patch(route('tickets.status', $ticket), ['status' => 'Closed'])->assertForbidden();
        $this->patch(route('tickets.status', $ticket), ['status' => 'Reopened'])->assertForbidden();
        $this->assertSame('Resolved', $ticket->fresh()->status);
    }

    public function test_internal_attachment_is_not_available_to_the_requester(): void
    {
        $ticket = $this->newTicket($this->employee);
        $comment = TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $this->head->id, 'body' => 'Internal diagnostic attachment', 'is_internal' => true]);
        $attachment = TicketAttachment::create(['ticket_id' => $ticket->id, 'uploaded_by' => $this->head->id, 'ticket_comment_id' => $comment->id, 'disk' => 'local', 'path' => 'tickets/'.$ticket->id.'/private-note.txt', 'original_name' => 'private-note.txt', 'mime_type' => 'text/plain', 'size' => 7]);

        $this->actingAs($this->employee)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
        $this->assertTrue(Gate::forUser($this->head)->allows('comment', [$ticket, true]));
        $this->assertFalse(Gate::forUser($this->employee)->allows('comment', [$ticket, true]));
    }

    public function test_user_creation_and_password_resets_use_one_time_setup_links(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'account-admin@test.invalid', $this->employee->department);
        $this->actingAs($admin)->post(route('users.store'), [
            'employee_number' => 'EMP-NEW-1', 'first_name' => 'Jamie', 'middle_name' => '', 'last_name' => 'Santos',
            'email' => 'jamie.santos@test.invalid', 'contact_number' => '09170000000', 'position' => 'Faculty',
            'department_id' => $this->employee->department_id, 'role_id' => $this->employee->role_id,
        ])->assertRedirect()->assertSessionHas('success', fn (string $message) => str_contains($message, 'no email was delivered')
            && str_contains($message, 'storage/logs/laravel.log'));

        $created = User::where('email', 'jamie.santos@test.invalid')->firstOrFail();
        $this->assertFalse(Hash::check('DemoOnly!2026', $created->password));
        Notification::assertSentTo($created, ResetPassword::class);
        $this->actingAs($this->employee)->post(route('users.password-reset', $created))->assertForbidden();
    }

    public function test_administrator_can_create_account_without_email_and_user_must_set_password_on_first_login(): void
    {
        Notification::fake();
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'no-email-admin@test.invalid', $this->employee->department);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'employee_number' => 'STU-NOEMAIL-1', 'first_name' => 'Taylor', 'middle_name' => '', 'last_name' => 'Reyes',
            'email' => '', 'contact_number' => '', 'position' => 'Student', 'department_id' => $this->employee->department_id,
            'role_id' => $this->employee->role_id,
        ])->assertRedirect()->assertSessionHas('temporary_password');

        $temporaryPassword = $this->app['session.store']->get('temporary_password');
        $user = User::where('employee_number', 'STU-NOEMAIL-1')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $user->password));
        Notification::assertNothingSent();
        auth()->logout();

        $this->post(route('login.store'), ['login' => $user->employee_number, 'password' => $temporaryPassword])
            ->assertRedirect(route('password.first-change'));
        $this->get(route('dashboard'))->assertRedirect(route('password.first-change'));
        $this->get(route('password.first-change'))->assertOk()->assertSee('Choose a new password');
        $this->put(route('password.first-change.update'), [
            'current_password' => $temporaryPassword,
            'password' => 'NewStrong!Password2026',
            'password_confirmation' => 'NewStrong!Password2026',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewStrong!Password2026', $user->password));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_system_cannot_remove_its_last_active_administrator(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'last-admin@test.invalid', $this->employee->department);
        $facultyRole = Role::where('name', 'faculty_employee')->firstOrFail();

        $this->actingAs($this->employee)->patch(route('users.toggle', $admin))->assertForbidden();
        $this->actingAs($admin)->patch(route('users.toggle', $admin))->assertStatus(422);
        $this->put(route('users.update', $admin), [
            'employee_number' => $admin->employee_number,
            'first_name' => $admin->first_name,
            'middle_name' => $admin->middle_name,
            'last_name' => $admin->last_name,
            'email' => $admin->email,
            'contact_number' => $admin->contact_number,
            'position' => $admin->position,
            'department_id' => $admin->department_id,
            'role_id' => $facultyRole->id,
        ])->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->isRole('system_administrator'));
    }

    public function test_department_head_must_be_an_active_user_in_that_department(): void
    {
        $otherDepartment = Department::create(['code' => 'OTHER', 'name' => 'Other Department', 'is_active' => true]);
        $otherUser = $this->makeUser(Role::where('name', 'faculty_employee')->firstOrFail(), 'other-dept@test.invalid', $otherDepartment);
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'department-admin@test.invalid', $this->employee->department);

        $this->actingAs($admin)->put(route('departments.update', $this->employee->department), [
            'code' => 'TEST', 'name' => 'Test Department', 'description' => 'Updated details', 'is_active' => 1, 'head_user_id' => $this->employee->id,
        ])->assertRedirect();
        $this->assertSame($this->employee->id, $this->employee->department->fresh()->head_user_id);

        $this->put(route('departments.update', $this->employee->department), [
            'code' => 'TEST', 'name' => 'Test Department', 'description' => 'Updated details', 'is_active' => 1, 'head_user_id' => $otherUser->id,
        ])->assertSessionHasErrors('head_user_id');
    }

    public function test_administrator_can_create_update_search_deactivate_and_reactivate_departments(): void
    {
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'department-crud-admin@test.invalid', $this->employee->department);

        $this->actingAs($admin)->post(route('departments.store'), [
            'code' => 'LIB', 'name' => 'Library Services', 'description' => 'Library and learning resources.',
        ])->assertRedirect();
        $department = Department::where('code', 'LIB')->firstOrFail();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => true]);

        $this->get(route('departments.index', ['search' => 'LIB']))
            ->assertOk()
            ->assertSee('Library Services');

        $this->put(route('departments.update', $department), [
            'code' => 'LIB-2', 'name' => 'Library and Learning Services', 'description' => 'Updated description.',
            'head_user_id' => null, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'code' => 'LIB-2', 'name' => 'Library and Learning Services']);

        $this->delete(route('departments.destroy', $department))->assertRedirect();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => false]);

        $this->put(route('departments.update', $department), [
            'code' => 'LIB-2', 'name' => 'Library and Learning Services', 'description' => 'Updated description.',
            'head_user_id' => null, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.deactivated']);
    }

    public function test_administrator_can_update_deactivate_and_reactivate_user_accounts(): void
    {
        Notification::fake();
        $admin = $this->makeUser(Role::where('name', 'system_administrator')->firstOrFail(), 'user-crud-admin@test.invalid', $this->employee->department);
        $technicianRole = Role::where('name', 'mis_technician')->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), [
            'employee_number' => 'EMP-CRUD-1', 'first_name' => 'Morgan', 'middle_name' => '', 'last_name' => 'Diaz',
            'email' => 'morgan.diaz@test.invalid', 'contact_number' => '09170000001', 'position' => 'Support',
            'department_id' => $this->employee->department_id, 'role_id' => $this->employee->role_id,
        ])->assertRedirect();
        $user = User::where('email', 'morgan.diaz@test.invalid')->firstOrFail();

        $this->actingAs($this->employee)->get(route('users.edit', $user))->assertForbidden();
        $this->actingAs($admin)->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Save changes')
            ->assertSee('col-12 col-md-4');

        $this->put(route('users.update', $user), [
            'employee_number' => 'EMP-CRUD-1', 'first_name' => 'Morgan', 'middle_name' => 'Lee', 'last_name' => 'Diaz',
            'email' => 'morgan.lee.diaz@test.invalid', 'contact_number' => '09170000002', 'position' => 'MIS Support',
            'department_id' => $this->employee->department_id, 'role_id' => $technicianRole->id,
        ])->assertRedirect();
        $user->refresh();
        $this->assertSame('Morgan Lee Diaz', $user->name);
        $this->assertTrue($user->isRole('mis_technician'));

        $this->patch(route('users.toggle', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($admin)->patch(route('users.toggle', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated']);
    }

    private function makeUser(Role $role, string $email, Department $department): User
    {
        return User::create(['role_id' => $role->id, 'department_id' => $department->id, 'employee_number' => strtoupper(substr(md5($email), 0, 10)), 'first_name' => 'Test', 'last_name' => 'User', 'name' => 'Test User', 'email' => $email, 'password' => Hash::make('test-password'), 'is_active' => true]);
    }

    private function newTicket(User $requester, string $status = 'Open'): Ticket
    {
        return Ticket::create(['ticket_number' => 'MIS-2026-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT), 'requester_id' => $requester->id, 'department_id' => $requester->department_id, 'ticket_category_id' => $this->category->id, 'ticket_priority_id' => $this->priority->id, 'status' => $status, 'subject' => 'Test request', 'description' => 'A test description.']);
    }
}
