<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantSwitchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'integer']]);
        $tenant = $request->user()->tenants()->wherePivot('is_active', true)->where('tenants.is_active', true)->where('tenants.id', $data['tenant_id'])->firstOrFail();
        Inertia::clearHistory();
        $request->session()->put('tenant_id', $tenant->id);
        $request->session()->forget('branch_id');
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        return to_route('dashboard')->with('success', 'Perusahaan aktif: '.$tenant->name);
    }
}
