<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchAccess
{
    public function __construct(private TenantContext $context) {}

    public function query(User $user): Builder
    {
        $query = Branch::where('is_active', true);
        if (! $this->context->tenant()?->is_active || ! $user->tenants()->where('tenants.id', $this->context->id())->wherePivot('is_active', true)->exists()) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->can('cabang.manage')) {
            return $query;
        }
        if (! Schema::hasTable('branch_user')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branches.id', DB::table('branch_user')->where('tenant_id', $this->context->id())->where('user_id', $user->id)->select('branch_id'));
    }

    public function find(User $user, int $id): Branch
    {
        return $this->query($user)->findOrFail($id);
    }
}
