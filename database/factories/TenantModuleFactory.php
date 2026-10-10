<?php

namespace Database\Factories;

use App\Models\TenantModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantModule> */
class TenantModuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'transaksi', 'is_enabled' => true];
    }
}
