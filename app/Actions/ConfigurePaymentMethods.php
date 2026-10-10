<?php

namespace App\Actions;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Schema;

class ConfigurePaymentMethods
{
    public function handle(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            return;
        }
        foreach (['cash' => 'Tunai', 'transfer' => 'Transfer bank', 'debit' => 'Debit / EDC', 'ewallet' => 'E-wallet'] as $kind => $name) {
            PaymentMethod::firstOrCreate(['name' => $name], ['kind' => $kind, 'is_active' => true]);
        }
    }
}
