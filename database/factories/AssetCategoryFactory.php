<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetCategory> */
class AssetCategoryFactory extends Factory
{
    protected $model = AssetCategory::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'is_active' => true];
    }
}
