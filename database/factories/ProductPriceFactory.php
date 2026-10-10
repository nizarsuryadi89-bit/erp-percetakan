<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPrice> */
class ProductPriceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'detail_product' => 'Harga reguler', 'variant' => '', 'machine' => '', 'print_side' => '', 'finishing' => '', 'qty_min' => '1', 'qty_max' => null, 'unit_price' => '10000.00', 'hpp' => '5000.00', 'minimum_charge' => '0.00', 'is_active' => true];
    }
}
