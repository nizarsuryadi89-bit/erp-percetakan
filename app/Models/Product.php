<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'category', 'unit_name', 'pricing_basis', 'tier_basis', 'dimension_unit', 'minimum_length', 'minimum_width', 'is_active'])]
class Product extends TenantModel
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['minimum_length' => 'decimal:4', 'minimum_width' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
