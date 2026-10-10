<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('erp.module_registry') as $code => $definition) {
            Module::firstOrCreate(['code' => $code], collect($definition)->except(['dependencies', 'steps'])->all());
        }
        foreach (config('erp.module_registry') as $code => $definition) {
            $module = Module::where('code', $code)->firstOrFail();
            $module->dependencies()->syncWithoutDetaching(Module::whereIn('code', $definition['dependencies'])->pluck('id')->all());
        }
    }
}
