<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CashierRequestFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'actor_id' => User::factory(), 'request_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', 'testing'), 'result' => []];
    }
}
