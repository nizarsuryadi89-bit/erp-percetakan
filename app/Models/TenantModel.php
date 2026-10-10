<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(app(TenantScope::class));
        static::saving(function (TenantModel $model): void {
            $tenantId = app(TenantContext::class)->id();
            if (! $tenantId || ($model->tenant_id && (int) $model->tenant_id !== $tenantId) || ($model->exists && (int) $model->getOriginal('tenant_id') !== $tenantId)) {
                throw new AuthorizationException('Data bukan milik perusahaan aktif.');
            }
            $model->tenant_id = $tenantId;
        });
        static::deleting(function (TenantModel $model): void {
            if ((int) $model->tenant_id !== app(TenantContext::class)->id()) {
                throw new AuthorizationException('Data bukan milik perusahaan aktif.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
