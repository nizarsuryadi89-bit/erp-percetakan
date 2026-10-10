<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('cabang.manage');

        return Inertia::render('foundation/branches', ['branches' => Branch::orderBy('name')->orderBy('id')->get()]);
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

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = Branch::where('is_active', true)->findOrFail($data['branch_id']);
        $request->session()->put('branch_id', $branch->id);

        return to_route('dashboard')->with('success', 'Cabang aktif: '.$branch->name);
    }
}
