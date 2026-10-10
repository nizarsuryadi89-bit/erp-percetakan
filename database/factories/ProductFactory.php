<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('PRD-######'), 'name' => 'Produk '.fake()->word(), 'category' => 'Digital Print', 'unit_name' => 'Lembar', 'pricing_basis' => 'quantity', 'tier_basis' => 'quantity', 'dimension_unit' => 'cm', 'minimum_length' => '0', 'minimum_width' => '0', 'is_active' => true];
    }

    public function area(): static
    {
        return $this->state(fn (): array => ['pricing_basis' => 'area', 'tier_basis' => 'billable', 'unit_name' => 'm²']);
    }

    public function length(): static
    {
        return $this->state(fn (): array => ['pricing_basis' => 'length', 'tier_basis' => 'billable', 'unit_name' => 'Meter']);
    }
}
