<?php

namespace Database\Seeders;

use App\Models\Branch;

class BranchSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $this->eachTenant(fn () => Branch::firstOrCreate(['code' => 'UTAMA'], ['name' => 'Cabang Utama', 'is_active' => true]));
    }
}
