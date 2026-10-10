<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;

class ProductPriceSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        $this->eachTenant(function (Tenant $tenant): void {
            $product = Product::where('code', 'CONTOH-BANNER')->first();
            if ($product) {
                $product->prices()->firstOrCreate(['detail_product' => 'Harga contoh — lengkapi sebelum digunakan'], ['qty_min' => '1', 'qty_max' => null, 'unit_price' => '0', 'hpp' => '0', 'minimum_charge' => '0', 'is_active' => false]);
            }
        });
    }
}
