<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\PrintOrder;
use App\Models\User;

class DeskprintAccess
{
    public function __construct(private ModuleAccess $modules, private BranchAccess $branches, private TenantContext $context) {}

    public function branch(User $actor, int $branchId, bool $write = false): Branch
    {
        abort_unless($this->modules->allows($actor, 'POS'), 403);
        abort_if($write && ! $actor->can('spk.manage'), 403);

        return $this->branches->find($actor, $branchId);
    }

    public function order(User $actor, PrintOrder $order, bool $write = false): Branch
    {
        abort_unless((int) $order->tenant_id === $this->context->id(), 404);

        return $this->branch($actor, $order->branch_id, $write);
    }
}
