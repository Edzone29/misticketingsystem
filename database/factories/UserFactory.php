<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $middle = fake()->optional()->firstName();
        $last = fake()->lastName();

        return ['role_id' => Role::factory(), 'department_id' => Department::factory(), 'employee_number' => fake()->unique()->bothify('EMP-####??'), 'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'name' => trim($first.' '.($middle ?? '').' '.$last), 'email' => fake()->unique()->safeEmail(), 'contact_number' => fake()->optional()->numerify('09#########'), 'position' => fake()->jobTitle(), 'email_verified_at' => now(), 'password' => static::$password ??= Hash::make('TestPassword!2026'), 'is_active' => true, 'remember_token' => Str::random(10)];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function technician(): static
    {
        return $this->state(fn (array $attributes) => ['role_id' => Role::firstOrCreate(['name' => 'mis_technician'], ['label' => 'MIS Technician'])->id]);
    }

    public function misHead(): static
    {
        return $this->state(fn (array $attributes) => ['role_id' => Role::firstOrCreate(['name' => 'mis_head'], ['label' => 'MIS Head / Supervisor'])->id]);
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => ['role_id' => Role::firstOrCreate(['name' => 'system_administrator'], ['label' => 'System Administrator'])->id]);
    }
}
