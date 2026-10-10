<?php

namespace App\Support;

use App\Models\Module;
use App\Models\TenantModuleEntitlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ModuleRegistry
{
    public function isReady(): bool
    {
        foreach (['modules', 'module_dependencies', 'tenant_module_entitlements', 'platform_administrators'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /** @return Collection<int, Module> */
    public function modules(): Collection
    {
        if (! $this->isReady()) {
            return new Collection;
        }

        return Module::with('dependencies')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function resolve(string $code): ?Module
    {
        return $this->modules()->first(fn (Module $module): bool => $module->code === $code || in_array($code, $module->feature_codes, true));
    }

    /** @return list<string> */
    public function requiredCodes(string $code): array
    {
        $modules = $this->modules()->keyBy('code');
        $required = [];
        $visiting = [];
        $visit = function (string $current) use (&$visit, &$required, &$visiting, $modules): void {
            if (isset($visiting[$current]) || ! $modules->has($current)) {
                throw ValidationException::withMessages(['module_codes' => 'Dependensi modul tidak valid. Hubungi developer.']);
            }
            if (in_array($current, $required, true)) {
                return;
            }
            $visiting[$current] = true;
            foreach ($modules[$current]->dependencies as $dependency) {
                $visit($dependency->code);
            }
            unset($visiting[$current]);
            $required[] = $current;
        };
        $visit($code);

        return $required;
    }

    /** @return list<string> */
    public function availableCodes(): array
    {
        if (! $this->isReady()) {
            return [];
        }
        $modules = $this->modules();
        $active = $modules->where('is_active', true)->pluck('code')->all();
        $grants = TenantModuleEntitlement::with('module')->get()->filter(fn (TenantModuleEntitlement $entitlement): bool => $entitlement->isUsable())->map(fn (TenantModuleEntitlement $entitlement): string => $entitlement->module->code)->all();
        $available = array_values(array_intersect($active, $grants));

        return array_values(array_filter($available, function (string $code) use ($available): bool {
            try {
                return ! array_diff($this->requiredCodes($code), $available);
            } catch (ValidationException) {
                return false;
            }
        }));
    }

    /** @return array<string, array{name: string, description: string, dependencies: list<string>, steps: list<string>}> */
    public function tenantCatalog(): array
    {
        $catalog = [];
        foreach ($this->modules() as $module) {
            $catalog[$module->code] = ['name' => $module->name, 'description' => $module->description, 'dependencies' => $module->dependencies->pluck('code')->all(), 'steps' => config('erp.module_registry.'.$module->code.'.steps', [])];
        }

        return $catalog;
    }
}
