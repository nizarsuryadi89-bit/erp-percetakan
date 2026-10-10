<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'print_order_id', 'issued_by', 'issuer_name', 'number', 'order_number', 'company_snapshot', 'customer_snapshot', 'items_snapshot', 'notes', 'total', 'status', 'payment_status', 'paid_amount', 'balance', 'issued_at'])]
class Invoice extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['company_snapshot' => 'array', 'customer_snapshot' => 'array', 'items_snapshot' => 'array', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'balance' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    /** @return array{paid:string,balance:string,payment_status:string,collectible:string} */
    public function summary(): array
    {
        $paid = BigDecimal::zero();
        foreach ($this->payments as $entry) {
            $paid = $entry->entry_type === 'payment' ? $paid->plus($entry->amount) : $paid->minus($entry->amount);
        }
        $balance = BigDecimal::of($this->total)->minus($paid);
        if ($paid->isNegative() || $balance->isNegative()) {
            throw new \LogicException('Saldo ledger tidak valid. Hubungi pengelola.');
        }

        return ['paid' => (string) $paid->toScale(2), 'balance' => (string) $balance->toScale(2), 'payment_status' => $paid->isZero() ? 'unpaid' : ($balance->isZero() ? 'paid' : 'partially_paid'), 'collectible' => $this->status === 'void' ? '0.00' : (string) $balance->toScale(2)];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (Invoice $invoice): void {
            if (array_diff(array_keys($invoice->getDirty()), ['status', 'payment_status', 'paid_amount', 'balance', 'updated_at'])) {
                throw new \LogicException('Snapshot invoice tidak dapat ditimpa.');
            }
            if ($invoice->isDirty('status') && ($invoice->getOriginal('status') !== 'issued' || $invoice->status !== 'void')) {
                throw new \LogicException('Invoice void tidak dapat diaktifkan ulang.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Invoice historis tidak boleh dihapus.'));
    }
}
