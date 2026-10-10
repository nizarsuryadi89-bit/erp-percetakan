<?php

namespace Database\Seeders;

use App\Models\License;

class LicenseSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $this->eachTenant(fn () => License::firstOrCreate([], ['package_name' => config('erp.package_name'), 'price' => config('erp.license_price'), 'status' => 'pending']));
    }
}
