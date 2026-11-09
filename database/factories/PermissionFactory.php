<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return ['name' => str_replace(' ', '_', strtolower($label)), 'label' => ucwords($label)];
    }
}
