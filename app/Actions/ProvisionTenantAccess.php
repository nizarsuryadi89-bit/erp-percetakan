<?php

namespace App\Actions;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

class ProvisionTenantAccess
{
    public function __construct(private TenantContext $context, private ConfigureTenantRoles $roles) {}

    public function handle(Tenant $tenant): void
    {
        if ($this->context->id() !== $tenant->id) {
            throw new AuthorizationException('Konteks perusahaan tidak sesuai.');
        }
        $this->roles->handle($tenant);
        foreach (Module::orderBy('id')->get() as $module) {
            TenantModuleEntitlement::firstOrCreate(['module_id' => $module->id], ['is_granted' => false, 'is_enabled' => true]);
        }
    }
}
