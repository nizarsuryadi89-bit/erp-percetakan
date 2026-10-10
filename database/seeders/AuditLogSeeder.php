<?php

namespace Database\Seeders;

use App\Models\AuditLog;

class AuditLogSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $this->eachTenant(fn () => AuditLog::firstOrCreate(['action' => 'fondasi.diinisialisasi'], ['metadata' => ['source' => 'seeder']]));
    }
}
