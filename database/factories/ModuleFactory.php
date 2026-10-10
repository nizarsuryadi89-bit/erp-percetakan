<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Module> */
class ModuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'CUSTOM_'.fake()->unique()->numerify('######'), 'name' => 'Modul '.fake()->word(), 'description' => 'Modul tambahan perusahaan.', 'feature_codes' => [], 'sort_order' => 100, 'is_active' => true];
    }
}
