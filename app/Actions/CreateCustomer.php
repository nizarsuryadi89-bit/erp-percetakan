<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;

class CreateCustomer
{
    public function __construct(private DeskprintTransaction $transaction) {}

    /** @param array{branch_id:int|string,request_key:string,name:string,phone?:?string,address?:?string} $data */
    public function handle(User $actor, array $data): Customer
    {
        $result = $this->transaction->run($actor, (int) $data['branch_id'], 'customer.create', $data, function () use ($actor, $data): array {
            $customer = Customer::create(['branch_id' => $data['branch_id'], 'name' => $data['name'], 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null]);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'pelanggan.dibuat', 'metadata' => ['customer_id' => $customer->id, 'branch_id' => $customer->branch_id]]);

            return ['customer_id' => $customer->id];
        });

        return Customer::findOrFail($result['customer_id']);
    }
}
