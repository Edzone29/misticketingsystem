<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRecord> */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    public function definition(): array
    {
        return ['asset_id' => Asset::factory(), 'maintenance_schedule_id' => null, 'technician_id' => User::factory(), 'performed_at' => now(), 'findings' => fake()->sentence(), 'actions_performed' => fake()->paragraph(), 'next_maintenance_on' => fake()->optional()->dateTimeBetween('+1 month', '+1 year')?->format('Y-m-d'), 'status' => 'Completed'];
    }

    public function forSchedule(MaintenanceSchedule $schedule): static
    {
        return $this->state(fn (array $attributes) => ['asset_id' => $schedule->asset_id, 'maintenance_schedule_id' => $schedule->id]);
    }
}
