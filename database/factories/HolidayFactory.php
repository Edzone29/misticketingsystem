<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Holiday> */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return ['date' => fake()->unique()->dateTimeBetween('today', '+18 months')->format('Y-m-d'), 'name' => fake()->unique()->sentence(3), 'is_working_day' => false];
    }
}
