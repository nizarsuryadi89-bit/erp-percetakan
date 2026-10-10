<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['kind' => 'cash', 'name' => 'Tunai '.fake()->uuid(), 'details' => null, 'is_active' => true];
    }
}
