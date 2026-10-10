<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BranchMembershipController extends Controller
{
    public function update(Request $request, Branch $branch, TenantContext $context): RedirectResponse
    {
        Gate::authorize('cabang.manage');
        Gate::authorize('pengguna.manage');
        $data = $request->validate(['user_ids' => ['present', 'array', 'max:500'], 'user_ids.*' => ['required', 'integer', 'distinct']]);
        DB::transaction(function () use ($request, $branch, $context, $data): void {
            DB::table('branches')->where('tenant_id', $context->id())->where('id', $branch->id)->increment('spk_sequence', 0);
            $ids = array_map('intval', $data['user_ids']);
            $allowed = $context->tenant()->users()->wherePivot('is_active', true)->whereIn('users.id', $ids)->pluck('users.id')->all();
            if (array_diff($ids, $allowed)) {
                throw ValidationException::withMessages(['user_ids' => 'Pilih hanya anggota aktif perusahaan ini.']);
            }
            DB::table('branch_user')->where('tenant_id', $context->id())->where('branch_id', $branch->id)->delete();
            foreach ($ids as $id) {
                DB::table('branch_user')->insert(['tenant_id' => $context->id(), 'branch_id' => $branch->id, 'user_id' => $id]);
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'cabang.akses_diperbarui', 'metadata' => ['branch_id' => $branch->id, 'user_ids' => $ids]]);
        }, attempts: 5);

        return back()->with('success','Assignment cabang disimpan.');
    }
}
