<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceEventFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['invoice_id' => Invoice::factory(), 'branch_id' => fn (array $data) => Invoice::findOrFail($data['invoice_id'])->branch_id, 'actor_name' => 'Kasir Uji', 'action' => 'invoice.diterbitkan', 'metadata' => []];
    }
}
