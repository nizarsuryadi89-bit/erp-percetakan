<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\TenantModuleEntitlement;

class TenantModuleEntitlementSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $modules = Module::orderBy('id')->get();
        $this->eachTenant(function () use ($modules): void {
            foreach ($modules as $module) {
                TenantModuleEntitlement::firstOrCreate(['module_id' => $module->id], ['is_granted' => false, 'is_enabled' => true]);
            }
        });
    }
}
