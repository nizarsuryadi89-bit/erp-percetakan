<?php

namespace App\Console\Commands;

use App\Models\PlatformAdministrator;
use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformAccess extends Command
{
    protected $signature = 'erp:platform-access {email : Email akun developer yang sudah terdaftar} {--revoke : Cabut akses platform}';

    protected $description = 'Berikan atau cabut hak panel developer secara eksplisit melalui terminal';

    public function handle(): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user || ! $user->hasVerifiedEmail()) {
            $this->error('Akun tidak ditemukan atau email belum terverifikasi.');

            return self::FAILURE;
        }
        PlatformAdministrator::updateOrCreate(['user_id' => $user->id], ['is_active' => ! $this->option('revoke')]);
        $this->info($this->option('revoke') ? 'Akses panel developer dicabut.' : 'Akses panel developer diberikan. Hak tenant tetap mengikuti membership.');

        return self::SUCCESS;
    }
}
