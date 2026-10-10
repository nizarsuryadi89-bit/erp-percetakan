<?php

namespace Database\Factories;

use App\Actions\CalculateProductPrice;
use App\Models\PrintOrder;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrintOrderItemFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['print_order_id' => PrintOrder::factory(), 'product_id' => Product::factory(), 'state' => 'active', 'snapshot' => function (array $data): array {
            $product = Product::findOrFail($data['product_id']);
            ProductPrice::factory()->for($product)->create(['unit_price' => '1000', 'hpp' => '500']);

            return app(CalculateProductPrice::class)->handle($product, ['qty' => '1']);
        }, 'cost_snapshot' => ['hpp' => '500.00']];
    }
}
