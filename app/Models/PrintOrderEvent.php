<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['print_order_id', 'actor_id', 'actor_name', 'action', 'metadata'])]
#[Hidden([])]
class PrintOrderEvent extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Riwayat SPK tidak dapat diubah.'));
        static::deleting(fn () => throw new \LogicException('Riwayat SPK tidak dapat dihapus.'));
    }
}
