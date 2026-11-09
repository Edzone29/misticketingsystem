<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        foreach ([
            'system_administrator' => 'System Administrator', 'mis_head' => 'MIS Head / Supervisor', 'mis_technician' => 'MIS Technician', 'faculty_employee' => 'Faculty / Employee', 'student' => 'Student',
        ] as $name => $label) {
            $roles[$name] = Role::updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $permissionLabels = [
            'users.manage' => 'Manage user accounts', 'departments.manage' => 'Manage departments', 'roles.manage' => 'Manage roles and permissions', 'categories.manage' => 'Manage ticket categories',
            'settings.manage' => 'Manage system settings and SLA', 'assets.manage' => 'Manage IT assets', 'tickets.view_all' => 'View all tickets', 'tickets.view_assigned' => 'View assigned tickets',
            'tickets.view_own' => 'View own tickets', 'tickets.create' => 'Submit tickets', 'tickets.assign' => 'Assign and reassign tickets', 'tickets.prioritize' => 'Set ticket priority',
            'tickets.update' => 'Update assigned ticket status', 'reports.view' => 'View MIS reports', 'maintenance.manage' => 'Schedule maintenance', 'maintenance.update' => 'Update assigned maintenance',
            'knowledge.manage' => 'Manage knowledge articles', 'knowledge.view' => 'View knowledge articles', 'feedback.submit' => 'Submit service feedback',
        ];
        $permissions = [];
        foreach ($permissionLabels as $name => $label) {
            $permissions[$name] = Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }
        $rolePermissions = [
            'system_administrator' => array_keys($permissionLabels),
            'mis_head' => ['tickets.view_all', 'tickets.assign', 'tickets.prioritize', 'tickets.update', 'reports.view', 'maintenance.manage', 'knowledge.manage', 'knowledge.view'],
            'mis_technician' => ['tickets.view_assigned', 'tickets.update', 'maintenance.update', 'knowledge.view'],
            'faculty_employee' => ['tickets.view_own', 'tickets.create', 'knowledge.view', 'feedback.submit'],
            'student' => ['tickets.view_own', 'tickets.create', 'knowledge.view', 'feedback.submit'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            $roles[$roleName]->permissions()->sync(array_map(fn ($name) => $permissions[$name]->id, $names));
        }

        $department = Department::updateOrCreate(['code' => 'DEMO-MIS'], ['name' => 'DEMO - Information Technology', 'description' => 'Demonstration department for local testing. Replace with an approved university department before use.', 'is_active' => true]);
        $this->call(UniversityDepartmentsSeeder::class);
        foreach ([
            ['Hardware', 'Computer and peripheral equipment issues.'], ['Software', 'Software installation and troubleshooting.'], ['Network and Internet', 'Connectivity and campus network issues.'], ['Email and Account Management', 'University account access and email.'], ['System and Application Support', 'University systems and applications.'], ['Printer and Scanner', 'Printing and scanning equipment.'], ['CCTV and Technical Equipment', 'Security camera and related equipment.'], ['Website and Database', 'University website and database support.'], ['IT Equipment Request', 'Request for IT equipment.'], ['Preventive Maintenance', 'Scheduled equipment maintenance.'], ['Other Technical Concerns', 'Other MIS support requests.'],
        ] as [$name,$description]) {
            TicketCategory::updateOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }
        foreach ([['Critical', 15, 240], ['High', 30, 480], ['Medium', 240, 960], ['Low', 480, 2400]] as $i => $row) {
            [$name,$response,$resolution] = $row;
            $priority = TicketPriority::updateOrCreate(['name' => $name], ['response_minutes' => $response, 'resolution_minutes' => $resolution, 'sort_order' => $i + 1]);
            SlaPolicy::updateOrCreate(['ticket_priority_id' => $priority->id], ['response_minutes' => $response, 'resolution_minutes' => $resolution, 'enabled' => true]);
        }
        foreach ([['Computers and Laptops'], ['Network Equipment'], ['Printers and Scanners'], ['Audio Visual Equipment'], ['Other IT Equipment']] as [$name]) {
            DB::table('asset_categories')->updateOrInsert(['name' => $name], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Deliberate local-only demo accounts. Do not use these credentials in production.
        $password = Hash::make('DemoOnly!2026');
        $accounts = [
            ['system_administrator', 'DEMO System Administrator', 'admin@demo.university.test', 'DEM-ADMIN'],
            ['mis_head', 'DEMO MIS Supervisor', 'head@demo.university.test', 'DEM-HEAD'],
            ['mis_technician', 'DEMO MIS Technician', 'technician@demo.university.test', 'DEM-TECH'],
            ['faculty_employee', 'DEMO Faculty Employee', 'employee@demo.university.test', 'DEM-EMP'],
            ['student', 'DEMO Student', 'student@demo.university.test', 'DEM-STUDENT'],
        ];
        foreach ($accounts as [$role,$name,$email,$number]) {
            [$first,$middle,$last] = $this->splitName($name);
            User::updateOrCreate(['email' => $email], ['role_id' => $roles[$role]->id, 'department_id' => $department->id, 'employee_number' => $number, 'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'name' => $name, 'position' => 'DEMO TEST ACCOUNT', 'password' => $password, 'is_active' => true, 'email_verified_at' => now()]);
        }
        foreach (['university_name' => 'City of Ilagan College', 'system_name' => 'City of Ilagan College MIS Helpdesk', 'ticket_prefix' => 'MIS', 'working_days' => '1,2,3,4,5', 'working_start' => '08:00', 'working_end' => '17:00', 'reopen_days' => '7', 'sla_pause_statuses' => 'Pending User,On Hold', 'sla_reminder_minutes' => '30', 'sla_escalation_minutes' => '60', 'email_notifications' => '0'] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->call(KnowledgeBaseSampleSeeder::class);
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', $name);

        return [$parts[1] ?? $name, null, $parts[2] ?? 'Demo'];
    }
}
