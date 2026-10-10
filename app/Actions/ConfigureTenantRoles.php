<?php

namespace App\Actions;

use App\Models\Module;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ConfigureTenantRoles
{
    public function handle(Tenant $tenant): void
    {
        $modulePermissions = array_map(fn (string $code): string => $code.'.view', Module::orderBy('id')->pluck('code')->all());
        $management = ['perusahaan.manage', 'cabang.manage', 'pengguna.manage', 'modul.manage', 'produk.manage', 'spk.manage', 'invoice.issue', 'payment.record', 'payment.reverse', 'invoice.void', 'payment-methods.manage'];
        foreach ([...$modulePermissions, ...$management] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $grants = [
            'Owner' => [...$modulePermissions, ...$management],
            'Administrator' => [...$modulePermissions, ...$management],
            'Kasir' => ['POS.view', 'spk.manage', 'invoice.issue', 'payment.record'],
            'Operator Produksi' => ['PRODUCTION.view'],
            'Keuangan' => ['FINANCE.view', 'ANALYTICS.view'],
            'Read Only' => $modulePermissions,
        ];
        foreach ($grants as $name => $permissions) {
            $role = Role::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
