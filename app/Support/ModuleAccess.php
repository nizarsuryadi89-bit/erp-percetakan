<?php

namespace App\Support;

use App\Models\License;
use App\Models\User;

class ModuleAccess
{
    public function __construct(private TenantContext $context, private ModuleRegistry $registry) {}

    /** @return list<string> */
    public function codes(User $user): array
    {
        $tenant = $this->context->tenant();
        if (! $tenant?->is_active || ! $this->registry->isReady()
            || ! $user->tenants()->where('tenants.id', $tenant->id)->wherePivot('is_active', true)->exists()
            || ! License::first()?->isUsable()) {
            return [];
        }

        return array_values(array_filter($this->registry->availableCodes(), fn (string $code): bool => $user->can($code.'.view')));
    }

    public function allows(User $user, string $code): bool
    {
        $module = $this->registry->resolve($code);

        return $module && in_array($module->code, $this->codes($user), true);
    }
}
