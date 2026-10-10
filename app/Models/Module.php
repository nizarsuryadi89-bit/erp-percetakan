<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'description', 'feature_codes', 'sort_order', 'is_active'])]
class Module extends Model
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['feature_codes' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'module_dependencies', 'module_id', 'dependency_id');
    }
}
