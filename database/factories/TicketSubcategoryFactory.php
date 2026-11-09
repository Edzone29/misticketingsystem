<?php

namespace Database\Factories;

use App\Models\TicketCategory;
use App\Models\TicketSubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketSubcategory> */
class TicketSubcategoryFactory extends Factory
{
    protected $model = TicketSubcategory::class;

    public function definition(): array
    {
        return ['ticket_category_id' => TicketCategory::factory(), 'name' => fake()->unique()->words(2, true), 'is_active' => true];
    }
}
