<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return ['asset_tag' => strtoupper(fake()->unique()->bothify('AST-######')), 'serial_number' => fake()->unique()->bothify('SN-########??'), 'name' => fake()->words(3, true), 'asset_category_id' => AssetCategory::factory(), 'brand' => fake()->company(), 'model' => fake()->bothify('Model-##??'), 'department_id' => Department::factory(), 'assigned_user_id' => null, 'building' => fake()->optional()->streetName(), 'room_number' => fake()->optional()->bothify('##??'), 'acquired_on' => fake()->dateTimeBetween('-8 years', '-1 year')->format('Y-m-d'), 'warranty_expires_on' => fake()->dateTimeBetween('-1 year', '+3 years')->format('Y-m-d'), 'condition' => 'Good', 'status' => 'In Service', 'remarks' => null];
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'Retired']);
    }
}
