<?php

namespace Database\Seeders;

use App\Models\TenantModule;

class TenantModuleSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $this->eachTenant(function (): void {
            foreach (array_keys(config('erp.modules')) as $code) {
                TenantModule::firstOrCreate(['code' => $code], ['is_enabled' => true]);
            }
        });
    }
}
