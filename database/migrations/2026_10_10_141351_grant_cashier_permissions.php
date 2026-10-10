<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice.issue', 'payment.record', 'payment.reverse', 'invoice.void', 'payment-methods.manage'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            $roles = in_array($name, ['invoice.issue', 'payment.record'], true) ? ['Owner', 'Administrator', 'Kasir'] : ['Owner', 'Administrator'];
            DB::table('roles')->whereNotNull('tenant_id')->where('guard_name', 'web')->whereIn('name', $roles)->orderBy('id')->each(function (object $role) use ($id): void {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $role->id]);
            });
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('guard_name', 'web')->whereIn('name', ['invoice.issue', 'payment.record', 'payment.reverse', 'invoice.void', 'payment-methods.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
