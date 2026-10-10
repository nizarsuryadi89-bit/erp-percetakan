<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;

class CashierAccess
{
    public function __construct(private ModuleAccess $modules, private BranchAccess $branches, private TenantContext $context) {}

    public function branch(User $actor, int $branchId, ?string $permission = null): Branch
    {
        abort_unless($this->modules->allows($actor, 'POS'), 403);
        abort_if($permission !== null && ! $actor->can($permission), 403);

        return $this->branches->find($actor, $branchId);
    }

    public function invoice(User $actor, Invoice $invoice, ?string $permission = null): Branch
    {
        abort_unless((int) $invoice->tenant_id === $this->context->id(), 404);

        return $this->branch($actor, $invoice->branch_id, $permission);
    }
}
