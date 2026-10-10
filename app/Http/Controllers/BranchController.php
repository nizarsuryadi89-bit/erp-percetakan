<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\BranchAccess;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('cabang.manage');

        return Inertia::render('foundation/branches', ['branches' => Branch::orderBy('name')->orderBy('id')->get(), 'members' => $request->user()->can('pengguna.manage') ? $context->tenant()->users()->wherePivot('is_active', true)->get(['users.id', 'name']) : [], 'assignments' => Schema::hasTable('branch_user') ? DB::table('branch_user')->where('tenant_id', $context->id())->get(['branch_id', 'user_id']) : [], 'canAssign' => $request->user()->can('pengguna.manage') && Schema::hasTable('branch_user')]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $branch = Branch::create($request->validated());
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'cabang.dibuat', 'metadata' => ['branch_id' => $branch->id]]);
        });

        return back()->with('success', 'Cabang berhasil ditambahkan.');
    }

    public function update(StoreBranchRequest $request, Branch $branch): RedirectResponse
    {
        DB::transaction(function () use ($request, $branch): void {
            $branch->update($request->validated());
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'cabang.diperbarui', 'metadata' => ['branch_id' => $branch->id]]);
        });

        return back()->with('success', 'Cabang berhasil diperbarui.');
    }

    public function switch(Request $request, BranchAccess $access): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = $access->find($request->user(), (int) $data['branch_id']);
        Inertia::clearHistory();
        $request->session()->put('branch_id', $branch->id);

        return to_route('dashboard')->with('success', 'Cabang aktif: '.$branch->name);
    }
}
