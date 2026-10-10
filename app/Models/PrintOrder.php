<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

#[Fillable(['branch_id', 'customer_id', 'created_by', 'number', 'method', 'marketplace', 'customer_snapshot', 'company_snapshot', 'creator_name', 'status', 'notes', 'deadline', 'total', 'version'])]
#[Hidden([])]
class PrintOrder extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['marketplace' => 'array', 'customer_snapshot' => 'array', 'company_snapshot' => 'array', 'deadline' => 'datetime', 'total' => 'decimal:2', 'version' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrintOrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PrintOrderEvent::class);
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (PrintOrder $order): void {
            if ($order->isDirty('status') && $order->status === 'cancelled' && Schema::hasTable('invoices') && Invoice::where('print_order_id', $order->id)->where('status', 'issued')->exists()) {
                throw new \LogicException('SPK memiliki invoice aktif dan tidak dapat dibatalkan.');
            }
            if (array_diff(array_keys($order->getDirty()), ['status', 'total', 'version', 'updated_at'])) {
                throw new \LogicException('Header SPK tidak dapat ditimpa.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Riwayat SPK tidak boleh dihapus.'));
    }
}
