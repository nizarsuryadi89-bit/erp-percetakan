<?php

namespace Database\Factories;

use App\Models\PlatformAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformAuditLog> */
class PlatformAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['action' => 'platform.akses_diberikan', 'metadata' => ['source' => 'test']];
    }
}
