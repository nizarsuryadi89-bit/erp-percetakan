<?php

namespace App\Providers;

use App\Support\TenantContext;
use App\Models\User;
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
        Gate::define('platform.manage', fn (User $user): bool => $user->isPlatformAdministrator());
    }
}
