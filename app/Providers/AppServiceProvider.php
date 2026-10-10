<?php

namespace App\Providers;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($ability === 'platform.manage') {
                return $user->isPlatformAdministrator();
            }

            return $user->checkPermissionTo($ability) ?: null;
        });
        Gate::define('platform.manage', fn (User $user): bool => $user->isPlatformAdministrator());
    }
}
