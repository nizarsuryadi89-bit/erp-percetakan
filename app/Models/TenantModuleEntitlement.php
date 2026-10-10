<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['module_id', 'is_granted', 'is_enabled', 'starts_at', 'expires_at', 'granted_by'])]
class TenantModuleEntitlement extends TenantModel
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_granted' => 'boolean', 'is_enabled' => 'boolean', 'starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function isUsable(): bool
    {
        return $this->is_granted && $this->is_enabled
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo(now()))
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
