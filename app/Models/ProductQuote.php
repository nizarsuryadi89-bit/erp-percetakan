<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['product_id', 'created_by', 'snapshot', 'cost_snapshot'])]
class ProductQuote extends TenantModel
{
    use HasFactory;

    protected $hidden = ['cost_snapshot'];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (ProductQuote $quote): void {
            $quote->uuid = (string) Str::uuid();
        });
        static::updating(function (): void {
            throw new LogicException('Hasil perhitungan tersimpan tidak dapat diubah. Buat perhitungan baru.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'cost_snapshot' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, mixed> */
    public function publicSnapshot(): array
    {
        return [...$this->snapshot, 'uuid' => $this->uuid, 'quoted_at' => $this->created_at->toISOString()];
    }
}
