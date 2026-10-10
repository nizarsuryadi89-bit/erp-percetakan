<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\TenantModuleEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantModuleEntitlement> */
class TenantModuleEntitlementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['module_id' => Module::factory(), 'is_granted' => true, 'is_enabled' => true, 'starts_at' => null, 'expires_at' => null];
    }
}
