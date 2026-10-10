<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoicePaymentFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['invoice_id' => Invoice::factory(), 'branch_id' => fn (array $data) => Invoice::findOrFail($data['invoice_id'])->branch_id, 'payment_method_id' => PaymentMethod::factory(), 'actor_name' => 'Kasir Uji', 'entry_type' => 'payment', 'amount' => '10000.00', 'method_snapshot' => ['kind' => 'cash', 'name' => 'Tunai', 'details' => null], 'paid_at' => now()];
    }
}
