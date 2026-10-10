<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['print_order_id', 'product_id', 'replaces_item_id', 'state', 'snapshot', 'cost_snapshot', 'notes', 'process_location', 'deadline'])]
#[Hidden(['cost_snapshot'])]
class PrintOrderItem extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'cost_snapshot' => 'array', 'deadline' => 'datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (PrintOrderItem $item): void {
            if (array_diff(array_keys($item->getDirty()), ['state', 'updated_at'])) {
                throw new \LogicException('Snapshot item tidak dapat diubah; buat revisi baru.');
            }
            if ($item->isDirty('state') && ($item->getOriginal('state') !== 'active' || ! in_array($item->state, ['removed', 'superseded'], true))) {
                throw new \LogicException('Versi item lama tidak dapat diaktifkan ulang.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Riwayat item tidak boleh dihapus.'));
    }
}
