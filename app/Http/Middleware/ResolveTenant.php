<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $context, private PermissionRegistrar $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);
        $memberships = $user->tenants()->wherePivot('is_active', true)->where('tenants.is_active', true);
        $selected = $request->session()->get('tenant_id');
        $tenant = $selected ? $memberships->where('tenants.id', $selected)->first() : $memberships->orderBy('tenants.id')->first();
        if (! $tenant) {
            if ($selected) {
                $request->session()->forget('tenant_id');
                abort(403, 'Perusahaan tidak aktif atau keanggotaan Anda tidak tersedia.');
            }

            return redirect()->route($user->isPlatformAdministrator() ? 'platform.index' : 'companies.create');
        }
        $request->session()->put('tenant_id', $tenant->id);
        $this->context->set($tenant);
        $this->permissions->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        try {
            return $next($request);
        } finally {
            $this->context->set(null);
            $this->permissions->setPermissionsTeamId(null);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
