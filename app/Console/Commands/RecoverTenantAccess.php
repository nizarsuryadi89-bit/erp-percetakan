<?php

namespace App\Console\Commands;

use App\Actions\ProvisionTenantAccess;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RecoverTenantAccess extends Command
{
    protected $signature = 'erp:recover-access {tenant : UUID perusahaan} {--dry-run : Periksa tanpa mengubah data}';

    protected $description = 'Pulihkan role dan baris entitlement yang hilang tanpa memberikan grant baru';

    public function handle(TenantContext $context, ModuleRegistry $registry, ProvisionTenantAccess $provision, PermissionRegistrar $permissions): int
    {
        if (! $registry->isReady()) {
            $this->error('Fondasi akses belum tersedia. Jalankan rencana migrasi yang telah disetujui.');

            return self::FAILURE;
        }
        $tenant = Tenant::where('uuid', $this->argument('tenant'))->first();
        if (! $tenant) {
            $this->error('Perusahaan tidak ditemukan.');

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Pemeriksaan selesai. Recovery akan menyediakan role default dan entitlement kosong; grant, status aktif, masa akses dan lisensi existing dipertahankan.');

            return self::SUCCESS;
        }
        $previous = $context->tenant();
        $team = $permissions->getPermissionsTeamId();
        $context->set($tenant);
        $permissions->setPermissionsTeamId($tenant->id);
        try {
            DB::transaction(function () use ($tenant, $provision): void {
                Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $before = TenantModuleEntitlement::count();
                $provision->handle($tenant);
                AuditLog::create(['action' => 'akses.dipulihkan', 'metadata' => ['source' => 'artisan', 'entitlements_added' => TenantModuleEntitlement::count() - $before]]);
            });
        } finally {
            $context->set($previous);
            $permissions->setPermissionsTeamId($team);
        }
        $this->info('Provisioning selesai tanpa memberikan grant tambahan.');

        return self::SUCCESS;
    }
}
