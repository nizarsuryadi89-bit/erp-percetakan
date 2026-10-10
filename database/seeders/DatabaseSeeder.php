<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ModuleSeeder::class, TenantSeeder::class, BranchSeeder::class, LicenseSeeder::class, TenantModuleSeeder::class, TenantModuleEntitlementSeeder::class]);
    }

    protected function eachTenant(Closure $seed): void
    {
        $context = app(TenantContext::class);
        $previous = $context->tenant();
        try {
            Tenant::orderBy('id')->each(function (Tenant $tenant) use ($context, $seed): void {
                $context->set($tenant);
                $seed($tenant);
            });
        } finally {
            $context->set($previous);
        }
    }
}
