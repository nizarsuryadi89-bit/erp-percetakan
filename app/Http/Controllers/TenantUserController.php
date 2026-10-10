<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantUserController extends Controller
{
    public function index(TenantContext $context): Response
    {
        Gate::authorize('pengguna.manage');
        $users = $context->tenant()->users()->with('roles')->orderBy('name')->orderBy('users.id')->get();

        return Inertia::render('foundation/users', ['users' => $users->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_active' => (bool) $user->pivot->is_active, 'role' => $user->roles->first()?->name]), 'roles' => array_values(array_diff(config('erp.roles'), ['Owner']))]);
    }

    public function store(StoreTenantUserRequest $request, TenantContext $context): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $context): User {
            $data = $request->validated();
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $context->tenant()->users()->attach($user, ['is_active' => true]);
            $user->assignRole($data['role']);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'pengguna.dibuat', 'metadata' => ['user_id' => $user->id, 'role' => $data['role']]]);

            return $user;
        });
        $user->sendEmailVerificationNotification();

        return back()->with('success', 'Pengguna ditambahkan. Email verifikasi telah dikirim.');
    }

    public function update(Request $request, int $user, TenantContext $context): RedirectResponse
    {
        Gate::authorize('pengguna.manage');
        $member = $context->tenant()->users()->findOrFail($user);
        abort_if($member->id === $request->user()->id || $member->hasRole('Owner'), 403, 'Akun Anda sendiri dan Owner tidak dapat diubah dari menu ini.');
        $data = $request->validate(['role' => ['required', Rule::in(array_values(array_diff(config('erp.roles'), ['Owner'])))], 'is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $context, $member, $data): void {
            $member->syncRoles([$data['role']]);
            $context->tenant()->users()->updateExistingPivot($member->id, ['is_active' => $data['is_active']]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'pengguna.akses_diperbarui', 'metadata' => ['user_id' => $member->id, ...$data]]);
        });

        return back()->with('success', 'Hak akses pengguna diperbarui.');
    }
}
