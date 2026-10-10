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
        /** Preserve access already provisioned under the previous package. New companies receive no grants automatically. */
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) use ($ids): void {
            $existing = DB::table('tenant_modules')->where('tenant_id', $tenant->id)->pluck('code')->all();
            foreach (['POS' => ['transaksi'], 'PRODUCTION' => ['bom', 'routing', 'produksi'], 'FINANCE' => ['keuangan'], 'ANALYTICS' => ['laba_rugi']] as $code => $features) {
                if (array_intersect($features, $existing)) {
                    DB::table('tenant_module_entitlements')->insert(['tenant_id' => $tenant->id, 'module_id' => $ids[$code], 'is_granted' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_entitlements');
        Schema::dropIfExists('platform_administrators');
        Schema::dropIfExists('module_dependencies');
        Schema::dropIfExists('modules');
    }
};
