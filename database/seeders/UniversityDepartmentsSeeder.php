<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class UniversityDepartmentsSeeder extends Seeder
{
    /**
     * Seed the official City of Ilagan College departments.
     */
    public function run(): void
    {
        $departments = [
            'OFF-PRES' => 'Office of the President',
            'PHYS-ED' => 'Physical Education',
            'ACC-FIN' => 'Accounting and Finance',
            'BS-PSYCH' => 'BS Psychology',
            'BS-NURS' => 'BS Nursing',
            'GEN-ED' => 'General Education',
            'OSAS' => 'Office of Student Affairs and Services',
            'EVP-VPA' => 'Office of the EVP/VPA',
            'GEN-SERV-SEC' => 'General Services and Security',
            'ENG-GEN-SERV' => 'Engineering / General Services',
            'GUID-COUNS' => 'Guidance and Counseling Office',
            'REG-OFFICE' => "Registrar's Office",
            'LAB' => 'Laboratory',
            'BS-NURS-NSTP' => 'BS Nursing / NSTP',
            'HRMO' => 'Human Resource Management Office',
            'BS-MEDTECH' => 'BS Medical Technology',
            'RDE-EXT-PROG' => 'Research and Development / Extension Program',
            'LIBRARY' => 'Library',
            'CLINIC' => 'Clinic',
            'ACADEMIC' => 'Academic Office',
            'BS-RADTECH' => 'BS Radiologic Technology',
            'RDET-COMM-EXT' => 'RDET / Community Extension',
            'CLINIC-HEALTH' => 'Clinic / Health Services',
            'BS-IS' => 'BS Information System',
            'VP-RDET' => 'VP for RDET Office',
            'MGMT-INFO-SYS' => 'Management Information Systems',
            'PHYS-SCI-LAB' => 'Physics / Science Laboratory',
            'SEC-GSO-STAFF' => 'Security Guard and GSO Staff',
            'ILAGANDA' => 'Ilaganda',
            'UTILITY' => 'Utility',
        ];

        foreach ($departments as $code => $name) {
            Department::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
