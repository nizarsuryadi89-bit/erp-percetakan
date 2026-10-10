<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;

class ProductSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        $this->eachTenant(function (Tenant $tenant): void {
            Product::firstOrCreate(['code' => 'CONTOH-BANNER'], ['name' => 'Contoh Banner', 'category' => 'Large Format', 'unit_name' => 'm²', 'pricing_basis' => 'area', 'tier_basis' => 'billable', 'dimension_unit' => 'cm', 'minimum_length' => '0', 'minimum_width' => '0', 'is_active' => false]);
        });
    }
}
