<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceSchedule> */
class MaintenanceScheduleFactory extends Factory
{
    protected $model = MaintenanceSchedule::class;

    public function definition(): array
    {
        return ['asset_id' => Asset::factory(), 'assigned_to' => User::factory()->technician(), 'title' => fake()->sentence(4), 'instructions' => fake()->optional()->paragraph(), 'scheduled_for' => fake()->dateTimeBetween('today', '+6 months')->format('Y-m-d'), 'status' => 'Scheduled'];
    }
}
