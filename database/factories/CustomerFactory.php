<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'name' => fake()->name(), 'phone' => '081234567890', 'address' => fake()->address()];
    }
}
