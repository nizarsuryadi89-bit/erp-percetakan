<?php

namespace App\Console\Commands;

use App\Models\PlatformAdministrator;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrantPlatformAccess extends Command
{
    protected $signature = 'erp:platform-access {email : Email akun developer yang sudah terdaftar} {--revoke : Cabut akses platform}';

    protected $description = 'Berikan atau cabut hak panel developer secara eksplisit melalui terminal';

    public function handle(): int
    {
        if (! Schema::hasTable('platform_administrators') || ! Schema::hasTable('platform_audit_logs')) {
            $this->error('Fondasi platform belum tersedia. Jalankan rencana migrasi yang telah disetujui.');

            return self::FAILURE;
        }
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user || ! $user->hasVerifiedEmail()) {
            $this->error('Akun tidak ditemukan atau email belum terverifikasi.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user): void {
            PlatformAdministrator::updateOrCreate(['user_id' => $user->id], ['is_active' => ! $this->option('revoke')]);
            PlatformAuditLog::create(['target_user_id' => $user->id, 'action' => $this->option('revoke') ? 'platform.akses_dicabut' : 'platform.akses_diberikan', 'metadata' => ['source' => 'artisan']]);
        });
        $this->info($this->option('revoke') ? 'Akses panel developer dicabut.' : 'Akses panel developer diberikan. Hak tenant tetap mengikuti membership.');

        return self::SUCCESS;
    }
}
