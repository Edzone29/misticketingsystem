<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Department';

        return ['code' => strtoupper(fake()->unique()->bothify('DEP-####')), 'name' => $name, 'description' => fake()->optional()->sentence(), 'head_user_id' => null, 'is_active' => true];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
