<?php

namespace App\Models;

use Database\Factories\TenantModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['code', 'is_enabled'])]
class TenantModule extends TenantModel
{
    /** @use HasFactory<TenantModuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
