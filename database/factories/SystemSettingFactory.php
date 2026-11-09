<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SystemSetting> */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(2), 'value' => fake()->sentence()];
    }
}
