<?php

namespace Database\Seeders;

use App\Actions\ConfigureTenantRoles;
use App\Models\Tenant;

class TenantSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $roles = app(ConfigureTenantRoles::class);
        $this->eachTenant(fn (Tenant $tenant) => $roles->handle($tenant));
    }
}
