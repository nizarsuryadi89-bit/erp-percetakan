<?php

namespace Database\Factories;

use App\Models\PrintOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvoiceFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['print_order_id' => PrintOrder::factory()->state(['status' => 'confirmed', 'total' => '82000.00']), 'branch_id' => fn (array $data) => PrintOrder::findOrFail($data['print_order_id'])->branch_id, 'number' => 'INV-TEST-'.Str::uuid(), 'order_number' => fn (array $data) => PrintOrder::findOrFail($data['print_order_id'])->number, 'issuer_name' => 'Kasir Uji', 'company_snapshot' => fn (array $data) => PrintOrder::findOrFail($data['print_order_id'])->company_snapshot, 'customer_snapshot' => fn (array $data) => PrintOrder::findOrFail($data['print_order_id'])->customer_snapshot, 'items_snapshot' => [], 'total' => '82000.00', 'status' => 'issued', 'payment_status' => 'unpaid', 'paid_amount' => '0.00', 'balance' => '82000.00', 'issued_at' => now()];
    }
}
