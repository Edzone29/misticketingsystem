<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Local evaluation data only. Run explicitly; do not use as a production inventory import.
 */
class SampleAssetsSeeder extends Seeder
{
    public function run(): void
    {
        $departmentId = Department::query()
            ->where('code', 'MGMT-INFO-SYS')
            ->value('id');

        $categoryIds = AssetCategory::query()
            ->whereIn('name', [
                'Computers and Laptops',
                'Network Equipment',
                'Printers and Scanners',
                'Audio Visual Equipment',
                'Other IT Equipment',
            ])
            ->pluck('id', 'name');

        $samples = [
            [
                'asset_tag' => 'DEMO-ASSET-001',
                'serial_number' => 'DEMO-SN-000001',
                'name' => 'Sample Desktop Workstation',
                'category' => 'Computers and Laptops',
                'brand' => 'Dell',
                'model' => 'OptiPlex 7010',
                'acquired_on' => '2024-06-15',
                'warranty_expires_on' => '2027-06-14',
                'condition' => 'Good',
                'status' => 'In Service',
            ],
            [
                'asset_tag' => 'DEMO-ASSET-002',
                'serial_number' => 'DEMO-SN-000002',
                'name' => 'Sample Faculty Laptop',
                'category' => 'Computers and Laptops',
                'brand' => 'Lenovo',
                'model' => 'ThinkPad E14',
                'acquired_on' => '2023-08-01',
                'warranty_expires_on' => '2026-07-31',
                'condition' => 'Good',
                'status' => 'In Service',
            ],
            [
                'asset_tag' => 'DEMO-ASSET-003',
                'serial_number' => 'DEMO-SN-000003',
                'name' => 'Sample Network Switch',
                'category' => 'Network Equipment',
                'brand' => 'Cisco',
                'model' => 'Catalyst 1000',
                'acquired_on' => '2022-10-21',
                'warranty_expires_on' => '2025-10-20',
                'condition' => 'Fair',
                'status' => 'In Service',
            ],
            [
                'asset_tag' => 'DEMO-ASSET-004',
                'serial_number' => 'DEMO-SN-000004',
                'name' => 'Sample Wireless Access Point',
                'category' => 'Network Equipment',
                'brand' => 'TP-Link',
                'model' => 'Omada EAP',
                'acquired_on' => '2024-02-12',
                'warranty_expires_on' => '2027-02-11',
                'condition' => 'Good',
                'status' => 'In Service',
            ],
            [
                'asset_tag' => 'DEMO-ASSET-005',
                'serial_number' => 'DEMO-SN-000005',
                'name' => 'Sample Multifunction Printer',
                'category' => 'Printers and Scanners',
                'brand' => 'Epson',
                'model' => 'EcoTank L5290',
                'acquired_on' => '2023-03-10',
                'warranty_expires_on' => '2026-03-09',
                'condition' => 'Fair',
                'status' => 'Under Repair',
            ],
            [
                'asset_tag' => 'DEMO-ASSET-006',
                'serial_number' => 'DEMO-SN-000006',
                'name' => 'Sample Classroom Projector',
                'category' => 'Audio Visual Equipment',
                'brand' => 'Epson',
                'model' => 'EB-E01',
                'acquired_on' => '2022-07-18',
                'warranty_expires_on' => '2025-07-17',
                'condition' => 'Needs Repair',
                'status' => 'In Storage',
            ],
            [
                'asset_tag' => 'DEMO-ASSET-007',
                'serial_number' => 'DEMO-SN-000007',
                'name' => 'Sample Uninterruptible Power Supply',
                'category' => 'Other IT Equipment',
                'brand' => 'APC',
                'model' => 'Back-UPS 650',
                'acquired_on' => '2021-11-05',
                'warranty_expires_on' => '2024-11-04',
                'condition' => 'Unserviceable',
                'status' => 'Retired',
            ],
        ];

        foreach ($samples as $sample) {
            $categoryId = $categoryIds->get($sample['category']);
            if (! $categoryId) {
                throw new \RuntimeException("Missing asset category: {$sample['category']}");
            }

            unset($sample['category']);
            Asset::updateOrCreate(
                ['asset_tag' => $sample['asset_tag']],
                [
                    ...$sample,
                    'asset_category_id' => $categoryId,
                    'department_id' => $departmentId,
                    'assigned_user_id' => null,
                    'building' => null,
                    'room_number' => null,
                    'remarks' => 'SAMPLE DATA for local evaluation. Replace with verified campus inventory details before operational use.',
                ],
            );
        }
    }
}
