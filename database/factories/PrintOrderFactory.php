<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PrintOrderFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'branch_id' => fn (array $data) => Customer::findOrFail($data['customer_id'])->branch_id, 'number' => 'TEST-'.Str::uuid(), 'method' => 'walk_in', 'creator_name' => 'Petugas Uji', 'customer_snapshot' => fn (array $data) => Customer::findOrFail($data['customer_id'])->only(['name', 'phone', 'address']), 'company_snapshot' => ['name' => 'Perusahaan Uji', 'branch_name' => 'Cabang Uji', 'timezone' => 'Asia/Jakarta'], 'status' => 'draft', 'version' => 1, 'total' => '0.00'];
    }
}
