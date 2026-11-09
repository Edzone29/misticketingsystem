<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $label = fake()->unique()->jobTitle();

        return ['name' => fake()->unique()->slug(2), 'label' => $label];
    }
}
