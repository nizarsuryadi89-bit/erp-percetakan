<?php

namespace Database\Factories;

use App\Models\PrintOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrintOrderEventFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['print_order_id' => PrintOrder::factory(), 'actor_name' => 'Petugas Uji', 'action' => 'spk.dibuat', 'metadata' => ['status' => 'draft']];
    }
}
