<?php

namespace Database\Factories;

use App\Actions\CalculateProductPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductQuote> */
class ProductQuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'created_by' => null,
            'snapshot' => function (array $attributes): array {
                $product = Product::findOrFail($attributes['product_id']);
                ProductPrice::factory()->for($product)->create(['unit_price' => '1000', 'hpp' => '500']);

                return app(CalculateProductPrice::class)->handle($product, ['qty' => '1']);
            },
            'cost_snapshot' => ['hpp' => '500.00'],
        ];
    }
}
