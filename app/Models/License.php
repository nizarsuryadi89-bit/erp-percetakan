<?php

namespace App\Models;

use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['package_name', 'price', 'status', 'activated_at', 'expires_at', 'support_ends_at'])]
class License extends TenantModel
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'activated_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'support_ends_at' => 'immutable_datetime'];
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->activated_at !== null
            && $this->activated_at->lessThanOrEqualTo(now())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
