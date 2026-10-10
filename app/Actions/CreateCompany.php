<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\License;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CreateCompany
{
    public function __construct(private TenantContext $context, private ConfigureTenantRoles $roles, private PermissionRegistrar $permissions) {}

    /** @param array{name: string, phone?: ?string, address?: ?string} $data */
    public function handle(User $owner, array $data): Tenant
    {
        $previous = $this->context->tenant();
        $previousTeam = $this->permissions->getPermissionsTeamId();
        try {
            return DB::transaction(function () use ($owner, $data): Tenant {
                $tenant = Tenant::create($data);
                $this->context->set($tenant);
                $this->permissions->setPermissionsTeamId($tenant->id);
                $owner->unsetRelation('roles')->unsetRelation('permissions');
                $tenant->users()->attach($owner, ['is_active' => true]);
                $this->roles->handle($tenant);
                $owner->assignRole('Owner');
                Branch::create(['code' => 'UTAMA', 'name' => 'Cabang Utama', 'is_active' => true]);
                License::create(['package_name' => config('erp.package_name'), 'price' => config('erp.license_price'), 'status' => 'pending']);
                foreach (array_keys(config('erp.modules')) as $code) {
                    TenantModule::create(['code' => $code, 'is_enabled' => true]);
                }
                AuditLog::create(['actor_id' => $owner->id, 'action' => 'perusahaan.dibuat', 'metadata' => ['name' => $tenant->name]]);

                return $tenant;
            });
        } finally {
            $this->context->set($previous);
            $this->permissions->setPermissionsTeamId($previousTeam);
            $owner->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
