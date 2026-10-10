<?php

namespace App\Http\Controllers;

use App\Actions\AssignTenantModules;
use App\Http\Requests\AssignTenantModulesRequest;
use App\Models\License;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $data['search'] ?? '';

        return Inertia::render('platform/index', ['companies' => Tenant::query()->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->withCount('users')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(), 'search' => $search]);
    }

    public function show(Tenant $tenant, TenantContext $context, ModuleRegistry $registry): Response
    {
        $previous = $context->tenant();
        $context->set($tenant);
        try {
            $license = License::firstOrFail();
            $entitlements = TenantModuleEntitlement::orderBy('id')->get();
            $expiresOn = $license->expires_at?->setTimezone($tenant->timezone)->format('Y-m-d');

            return Inertia::render('platform/company-modules', ['company' => $tenant, 'modules' => $registry->modules(), 'entitlements' => $entitlements, 'license' => $license, 'expiresOn' => $expiresOn]);
        } finally {
            $context->set($previous);
        }
    }

    public function update(AssignTenantModulesRequest $request, Tenant $tenant, AssignTenantModules $assign): RedirectResponse
    {
        $assign->handle($request->user(), $tenant, $request->validated());

        return back()->with('success', 'Hak modul perusahaan berhasil disimpan. Data operasional tetap tersimpan.');
    }
}
