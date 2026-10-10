<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CreateCompany
{
    public function __construct(private TenantContext $context, private ProvisionTenantAccess $provision, private PermissionRegistrar $permissions, private ModuleRegistry $registry) {}

    /** @param array{name: string, phone?: ?string, address?: ?string} $data */
    public function handle(User $owner, array $data): Tenant
    {
        abort_unless($this->registry->isReady(), 503, 'Akses perusahaan sedang disiapkan. Hubungi pengelola.');
        $previous = $this->context->tenant();
        $previousTeam = $this->permissions->getPermissionsTeamId();
        try {
            return DB::transaction(function () use ($owner, $data): Tenant {
                $tenant = Tenant::create($data);
                $this->context->set($tenant);
                $this->permissions->setPermissionsTeamId($tenant->id);
                $owner->unsetRelation('roles')->unsetRelation('permissions');
                $tenant->users()->attach($owner, ['is_active' => true]);
                $this->provision->handle($tenant);
                $owner->assignRole('Owner');
                Branch::create(['code' => 'UTAMA', 'name' => 'Cabang Utama', 'is_active' => true]);
                License::create(['package_name' => config('erp.package_name'), 'price' => config('erp.license_price'), 'status' => 'pending']);
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
