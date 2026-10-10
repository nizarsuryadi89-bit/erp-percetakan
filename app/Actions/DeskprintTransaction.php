<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\DeskprintRequest;
use App\Models\User;
use App\Support\DeskprintAccess;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeskprintTransaction
{
    public function __construct(private DeskprintAccess $access, private TenantContext $context) {}

    /** @param array<string,mixed> $data
     * @param Closure(Branch): array<string,int> $operation
     * @return array<string,int> */
    public function run(User $actor, int $branchId, string $command, array $data, Closure $operation): array
    {
        $this->access->branch($actor, $branchId, true);
        ksort($data);
        $fingerprint = hash('sha256', json_encode([$branchId, $command, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $branchId, $data, $operation, $fingerprint): array {
            /** Write before reads to serialize SQLite writers and lock this branch row on MySQL. */
            DB::table('branches')->where('tenant_id', $this->context->id())->where('id', $branchId)->increment('spk_sequence', 0);
            $branch = $this->access->branch($actor, $branchId, true);
            $receipt = DeskprintRequest::where('actor_id', $actor->id)->where('request_key', $data['request_key'])->first();
            if ($receipt) {
                if (! hash_equals($receipt->fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['request_key' => 'Permintaan ini sudah dipakai dengan isian berbeda. Muat ulang sebelum membuat operasi baru.']);
                }

                return $receipt->result;
            }
            $result = $operation($branch);
            try {
                DeskprintRequest::create(['branch_id' => $branchId, 'actor_id' => $actor->id, 'request_key' => $data['request_key'], 'fingerprint' => $fingerprint, 'result' => $result]);
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages(['request_key' => 'Permintaan ini sudah dipakai. Muat ulang sebelum membuat operasi baru.']);
            }

            return $result;
        }, attempts: 5);
    }
}
