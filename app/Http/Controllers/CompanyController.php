<?php

namespace App\Http\Controllers;

use App\Actions\CreateCompany;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('foundation/company-create');
    }

    public function store(StoreCompanyRequest $request, CreateCompany $create): RedirectResponse
    {
        $tenant = $create->handle($request->user(), $request->validated());
        $request->session()->put('tenant_id', $tenant->id);

        return to_route('dashboard')->with('success', 'Perusahaan berhasil dibuat.');
    }

    public function edit(TenantContext $context): Response
    {
        Gate::authorize('perusahaan.manage');

        return Inertia::render('foundation/company-edit', ['company' => $context->tenant()]);
    }

    public function update(Request $request, TenantContext $context): RedirectResponse
    {
        Gate::authorize('perusahaan.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $context, $data): void {
            $context->tenant()->update($data);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'perusahaan.diperbarui']);
        });

        return back()->with('success', 'Profil perusahaan diperbarui.');
    }
}
