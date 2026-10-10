<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['detail_product', 'variant', 'machine', 'print_side', 'finishing', 'qty_min', 'qty_max', 'unit_price', 'hpp', 'minimum_charge', 'is_active'])]
class ProductPrice extends TenantModel
{
    use HasFactory;

    protected $hidden = ['hpp'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['qty_min' => 'decimal:4', 'qty_max' => 'decimal:4', 'unit_price' => 'decimal:2', 'hpp' => 'decimal:2', 'minimum_charge' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
