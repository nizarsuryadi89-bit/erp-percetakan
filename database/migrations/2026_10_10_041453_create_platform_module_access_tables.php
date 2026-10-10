<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description');
            $table->json('feature_codes');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('module_dependencies', function (Blueprint $table): void {
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('dependency_id')->constrained('modules')->cascadeOnDelete();
            $table->primary(['module_id', 'dependency_id']);
        });
        Schema::create('platform_administrators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('tenant_module_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->restrictOnDelete();
            $table->boolean('is_granted')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });
        $definitions = [
            ['POS', 'Transaksi / POS', 'Order, SPK, kasir, invoice, dan piutang konsumen.', ['transaksi']],
            ['PRODUCTION', 'Produksi', 'BOM, routing, antrean produksi, finishing, dan QC.', ['bom', 'routing', 'produksi']],
            ['INVENTORY', 'Persediaan', 'Bahan, stok, penerimaan, dan pergerakan persediaan.', []],
            ['FINANCE', 'Keuangan', 'Pembayaran, piutang, pengeluaran, dan rekap keuangan.', ['keuangan']],
            ['CRM', 'CRM', 'Pelanggan, prospek, dan tindak lanjut penjualan.', []],
            ['HRM', 'SDM', 'Karyawan, kehadiran, dan pengelolaan sumber daya manusia.', []],
            ['ANALYTICS', 'Analitik / Laba Rugi', 'Laporan, pendapatan, HPP, biaya, dan laba per periode.', ['laba_rugi']],
            ['AI_ASSISTANT', 'Asisten AI', 'Bantuan analisis dan pencarian informasi ERP.', []],
        ];
        $ids = [];
        foreach ($definitions as $order => [$code, $name, $description, $features]) {
            $ids[$code] = DB::table('modules')->insertGetId(['code' => $code, 'name' => $name, 'description' => $description, 'feature_codes' => json_encode($features), 'sort_order' => $order, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['PRODUCTION' => ['POS'], 'FINANCE' => ['POS'], 'ANALYTICS' => ['POS', 'PRODUCTION', 'FINANCE']] as $code => $dependencies) {
            foreach ($dependencies as $dependency) {
                DB::table('module_dependencies')->insert(['module_id' => $ids[$code], 'dependency_id' => $ids[$dependency]]);
            }
        }
        $modulePermissions = array_map(fn (string $code): string => $code.'.view', array_keys($ids));
        $management = ['perusahaan.manage', 'cabang.manage', 'pengguna.manage', 'modul.manage', 'produk.manage'];
        foreach ([...$modulePermissions, ...$management] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
        $roles = [
            'Owner' => [...$modulePermissions, ...$management],
            'Administrator' => [...$modulePermissions, ...$management],
            'Kasir' => ['POS.view'],
            'Operator Produksi' => ['PRODUCTION.view'],
            'Keuangan' => ['FINANCE.view', 'ANALYTICS.view'],
            'Read Only' => $modulePermissions,
        ];
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) use ($ids, $roles, $permissionIds): void {
            $legacy = DB::table('tenant_modules')->where('tenant_id', $tenant->id)->pluck('is_enabled', 'code')->map(fn ($enabled): bool => (bool) $enabled)->all();
            $grants = array_fill_keys(array_keys($ids), false);
            foreach (['POS' => ['transaksi'], 'PRODUCTION' => ['bom', 'routing', 'produksi'], 'FINANCE' => ['keuangan'], 'ANALYTICS' => ['laba_rugi']] as $code => $features) {
                $grants[$code] = collect($features)->every(fn (string $feature): bool => $legacy[$feature] ?? false);
            }
            $grants['PRODUCTION'] = $grants['PRODUCTION'] && $grants['POS'];
            $grants['FINANCE'] = $grants['FINANCE'] && $grants['POS'];
            $grants['ANALYTICS'] = $grants['ANALYTICS'] && $grants['POS'] && $grants['PRODUCTION'] && $grants['FINANCE'];
            foreach ($ids as $code => $id) {
                DB::table('tenant_module_entitlements')->insert(['tenant_id' => $tenant->id, 'module_id' => $id, 'is_granted' => $grants[$code], 'is_enabled' => $grants[$code], 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($roles as $name => $permissions) {
                $role = DB::table('roles')->where('tenant_id', $tenant->id)->where('name', $name)->where('guard_name', 'web')->first();
                $roleId = $role?->id ?? DB::table('roles')->insertGetId(['tenant_id' => $tenant->id, 'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
                foreach ($permissions as $permission) {
                    DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
                }
            }
            DB::table('audit_logs')->insert(['tenant_id' => $tenant->id, 'action' => 'modul.legacy_dikonversi', 'metadata' => json_encode(['legacy_enabled' => $legacy, 'canonical_grants' => $grants]), 'created_at' => now(), 'updated_at' => now()]);
        });
        app('cache')->store(config('permission.cache.store') === 'default' ? null : config('permission.cache.store'))->forget(config('permission.cache.key'));

    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_entitlements');
        Schema::dropIfExists('platform_administrators');
        Schema::dropIfExists('module_dependencies');
        Schema::dropIfExists('modules');
    }
};
