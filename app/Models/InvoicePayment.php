<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['branch_id', 'invoice_id', 'payment_method_id', 'actor_id', 'actor_name', 'entry_type', 'reverses_payment_id', 'amount', 'method_snapshot', 'reference', 'notes', 'reason', 'paid_at'])]
class InvoicePayment extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'method_snapshot' => 'array', 'paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Ledger pembayaran hanya dapat ditambah.'));
        static::deleting(fn () => throw new \LogicException('Bukti pembayaran tidak boleh dihapus.'));
    }
}
