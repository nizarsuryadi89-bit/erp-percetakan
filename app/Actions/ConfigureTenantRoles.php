<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ConfigureTenantRoles
{
    public function handle(Tenant $tenant): void
    {
        $modulePermissions = array_map(fn (string $code): string => $code.'.view', [...array_keys(config('erp.modules')), ...Module::orderBy('id')->pluck('code')->all()]);
        $management = ['perusahaan.manage', 'cabang.manage', 'pengguna.manage', 'modul.manage', 'produk.manage'];
        foreach ([...$modulePermissions, ...$management] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $grants = [
            'Owner' => [...$modulePermissions, ...$management],
            'Administrator' => [...$modulePermissions, ...$management],
            'Kasir' => ['transaksi.view', 'POS.view'],
            'Operator Produksi' => ['produksi.view', 'bom.view', 'routing.view', 'PRODUCTION.view'],
            'Keuangan' => ['keuangan.view', 'laba_rugi.view', 'FINANCE.view', 'ANALYTICS.view'],
            'Read Only' => $modulePermissions,
        ];
        foreach ($grants as $name => $permissions) {
            $role = Role::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
