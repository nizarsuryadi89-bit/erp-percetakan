<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<License> */
class LicenseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['package_name' => config('erp.package_name'), 'price' => config('erp.license_price'), 'status' => 'pending'];
    }
}
