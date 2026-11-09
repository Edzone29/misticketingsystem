<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Feedback;
use App\Models\Holiday;
use App\Models\KnowledgeBaseArticle;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketNumberCounter;
use App\Models\TicketPriority;
use App\Models\TicketSlaRecord;
use App\Models\TicketStatusHistory;
use App\Models\TicketSubcategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_factories_create_valid_foreign_key_graphs_and_model_relations(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);
        $department = Department::factory()->create();
        $requester = User::factory()->for($role)->for($department)->create();
        $head = User::factory()->misHead()->for($department)->create();
        $technician = User::factory()->technician()->for($department)->create();
        $department->update(['head_user_id' => $head->id]);

        $category = TicketCategory::factory()->create();
        $subcategory = TicketSubcategory::factory()->for($category, 'category')->create();
        $priority = TicketPriority::factory()->create();
        $policy = SlaPolicy::factory()->for($priority, 'priority')->create();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'department_id' => $department->id, 'ticket_category_id' => $category->id, 'ticket_subcategory_id' => $subcategory->id, 'ticket_priority_id' => $priority->id]);
        $assignment = TicketAssignment::factory()->create(['ticket_id' => $ticket->id, 'technician_id' => $technician->id, 'assigned_by' => $head->id]);
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $requester->id]);
        $attachment = TicketAttachment::factory()->create(['ticket_id' => $ticket->id, 'uploaded_by' => $requester->id, 'ticket_comment_id' => $comment->id]);
        $history = TicketStatusHistory::factory()->create(['ticket_id' => $ticket->id, 'changed_by' => $head->id]);
        $sla = TicketSlaRecord::factory()->create(['ticket_id' => $ticket->id]);

        $asset = Asset::factory()->create(['department_id' => $department->id, 'assigned_user_id' => $requester->id]);
        $ticket->assets()->attach($asset);
        $schedule = MaintenanceSchedule::factory()->create(['asset_id' => $asset->id, 'assigned_to' => $technician->id]);
        $maintenance = MaintenanceRecord::factory()->forSchedule($schedule)->create(['technician_id' => $technician->id]);
        $article = KnowledgeBaseArticle::factory()->create(['author_id' => $head->id]);
        $feedback = Feedback::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $requester->id]);
        $holiday = Holiday::factory()->create();
        $setting = SystemSetting::factory()->create();
        $audit = AuditLog::factory()->create(['user_id' => $head->id, 'subject_type' => Ticket::class, 'subject_id' => $ticket->id]);
        $counter = TicketNumberCounter::factory()->create();

        $this->assertTrue($ticket->fresh()->requester->is($requester));
        $this->assertTrue($department->fresh()->head->is($head));
        $this->assertSame($department->id, $requester->fresh()->department->id);
        $this->assertTrue($ticket->fresh()->category->is($category));
        $this->assertTrue($ticket->fresh()->subcategory->is($subcategory));
        $this->assertTrue($ticket->fresh()->priority->slaPolicy->is($policy));
        $this->assertTrue($assignment->fresh()->assigner->is($head));
        $this->assertTrue($ticket->fresh()->assignments->first()->is($assignment));
        $this->assertTrue($attachment->fresh()->ticketComment->is($comment));
        $this->assertTrue($comment->fresh()->ticket->is($ticket));
        $this->assertTrue($history->fresh()->changedBy->is($head));
        $this->assertTrue($sla->fresh()->ticket->is($ticket));
        $this->assertTrue($asset->fresh()->maintenanceRecords->first()->is($maintenance));
        $this->assertTrue($asset->fresh()->category->assets->contains('id', $asset->id));
        $this->assertTrue($ticket->fresh()->assets->contains('id', $asset->id));
        $this->assertTrue($schedule->fresh()->record->is($maintenance));
        $this->assertTrue($article->fresh()->author->is($head));
        $this->assertTrue($feedback->fresh()->user->is($requester));
        $this->assertTrue($audit->fresh()->subject->is($ticket));
        $this->assertNotEmpty($setting->getKey());
        $this->assertGreaterThanOrEqual(2020, (int) $counter->year);
        $this->assertSame($permission->id, $role->permissions()->first()->id);
        $this->assertSame($holiday->date->toDateString(), $holiday->fresh()->date->toDateString());
    }

    public function test_foreign_key_rejects_missing_parent_records(): void
    {
        $this->expectException(QueryException::class);
        DB::table('ticket_comments')->insert(['ticket_id' => 987654, 'user_id' => 987654, 'body' => 'orphan', 'is_internal' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_rejects_duplicate_maintenance_records_for_one_schedule(): void
    {
        $schedule = MaintenanceSchedule::factory()->create();
        MaintenanceRecord::factory()->forSchedule($schedule)->create();
        $this->expectException(QueryException::class);
        MaintenanceRecord::factory()->forSchedule($schedule)->create();
    }

    public function test_database_rejects_feedback_ratings_outside_one_to_five(): void
    {
        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('feedback')->insert(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'rating' => 6, 'comments' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_rejects_a_subcategory_from_another_ticket_category(): void
    {
        $category = TicketCategory::factory()->create();
        $otherCategory = TicketCategory::factory()->create();
        $subcategory = TicketSubcategory::factory()->for($otherCategory, 'category')->create();
        $ticket = Ticket::factory()->create(['ticket_category_id' => $category->id, 'ticket_subcategory_id' => null]);
        $this->expectException(QueryException::class);
        $ticket->update(['ticket_subcategory_id' => $subcategory->id]);
    }

    public function test_database_rejects_feedback_from_a_non_requester(): void
    {
        $ticket = Ticket::factory()->create();
        $otherUser = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('feedback')->insert(['ticket_id' => $ticket->id, 'user_id' => $otherUser->id, 'rating' => 5, 'comments' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_seeder_is_idempotent_and_does_not_seed_fake_tickets(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('knowledge_base_articles', 4);
        $this->assertDatabaseHas('knowledge_base_articles', ['slug' => 'sample-connect-to-campus-wifi', 'is_published' => true]);
        $this->assertDatabaseHas('system_settings', ['key' => 'sla_pause_statuses', 'value' => 'Pending User,On Hold']);
    }
}
