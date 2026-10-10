<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\License;
use App\Models\TenantModule;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private TenantContext $context) {}

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => ['user' => $request->user()],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'workspace' => fn (): array => $this->workspace($request),
        ];
    }

    /** @return array<string, mixed> */
    private function workspace(Request $request): array
    {
        $user = $request->user();
        $tenant = $this->context->tenant();
        $companies = $user?->tenants()->wherePivot('is_active', true)->where('tenants.is_active', true)->orderBy('name')->get(['tenants.id', 'uuid', 'name']) ?? collect();
        $branches = $tenant ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']) : collect();
        $branchId = $request->session()->get('branch_id');
        if ($tenant && ! $branches->contains('id', $branchId)) {
            $branchId = $branches->first()?->id;
            $request->session()->put('branch_id', $branchId);
        }
        $navigation = [['title' => 'Dashboard', 'href' => route('dashboard'), 'code' => 'dashboard']];
        $modules = $tenant ? TenantModule::where('is_enabled', true)->pluck('code')->all() : [];
        $licensed = $tenant && License::first()?->isUsable();
        if ($licensed) {
            foreach (config('erp.modules') as $code => $module) {
                if (in_array($code, $modules, true) && ! array_diff($module['dependencies'], $modules) && $user->can($code.'.view')) {
                    $navigation[] = ['title' => $module['name'], 'href' => route($code.'.index'), 'code' => $code];
                }
            }
        }
        $settings = [];
        foreach (['perusahaan' => ['Profil Perusahaan', 'companies.edit'], 'cabang' => ['Cabang', 'branches.index'], 'pengguna' => ['Pengguna & Hak Akses', 'tenant-users.index'], 'modul' => ['Modul & Lisensi', 'modules.index']] as $code => [$title, $route]) {
            if ($tenant && $user->can($code.'.manage')) {
                $settings[] = ['title' => $title, 'href' => route($route), 'code' => $code];
            }
        }

        return ['tenant' => $tenant?->only(['id', 'uuid', 'name']), 'companies' => $companies, 'branches' => $branches, 'branchId' => $branchId, 'navigation' => $navigation, 'settings' => $settings,
            'switchCompanyUrl' => route('companies.switch'), 'switchBranchUrl' => route('branches.switch'), 'createCompanyUrl' => route('companies.create'),
            'roles' => $tenant ? $user->getRoleNames() : []];
    }
}
