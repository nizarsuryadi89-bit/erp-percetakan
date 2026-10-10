<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\License;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ActivateLicense extends Command
{
    protected $signature = 'erp:activate-license {tenant : ID atau UUID perusahaan}';

    protected $description = 'Aktifkan akses perusahaan setelah persetujuan pengelola';

    public function handle(TenantContext $context): int
    {
        $id = (string) $this->argument('tenant');
        $tenant = Tenant::where('uuid', $id)->when(ctype_digit($id), fn (Builder $query): Builder => $query->orWhere('id', (int) $id))->first();
        if (! $tenant || ! $tenant->is_active) {
            $this->error('Perusahaan tidak ditemukan atau tidak aktif.');

            return self::FAILURE;
        }
        $previous = $context->tenant();
        $context->set($tenant);
        try {
            DB::transaction(function (): void {
                $license = License::lockForUpdate()->firstOrFail();
                $license->update(['status' => 'active', 'activated_at' => $license->activated_at ?? now()]);
                AuditLog::create(['action' => 'lisensi.diaktifkan', 'metadata' => ['source' => 'artisan']]);
            });
            $this->info('Lisensi '.$tenant->name.' aktif. Masa support tidak mengubah masa hak pakai.');

            return self::SUCCESS;
        } finally {
            $context->set($previous);
        }
    }
}
