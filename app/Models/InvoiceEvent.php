<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['branch_id', 'invoice_id', 'actor_id', 'actor_name', 'action', 'metadata'])]
class InvoiceEvent extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Audit invoice immutable.'));
        static::deleting(fn () => throw new \LogicException('Audit invoice tidak boleh dihapus.'));
    }
}
