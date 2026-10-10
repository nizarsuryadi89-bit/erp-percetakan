<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\License;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTenantModules
{
    public function __construct(private TenantContext $context, private ModuleRegistry $registry, private ProvisionTenantAccess $provision) {}

    /** @param array{module_codes: list<string>, access_status?: string, expires_at?: ?string, module_expiries?: array<string, ?string>} $data */
    public function handle(User $developer, Tenant $tenant, array $data): void
    {
        Gate::forUser($developer)->authorize('platform.manage');
        $previous = $this->context->tenant();
        $this->context->set($tenant);
        try {
            DB::transaction(function () use ($developer, $tenant, $data): void {
                Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $this->provision->handle($tenant);
                $modules = Module::orderBy('id')->lockForUpdate()->get();
                $selected = $data['module_codes'];
                if (array_diff($selected, $modules->where('is_active', true)->pluck('code')->all())) {
                    throw ValidationException::withMessages(['module_codes' => 'Pilihan berisi modul yang tidak tersedia.']);
                }
                foreach ($selected as $code) {
                    if ($missing = array_diff($this->registry->requiredCodes($code), $selected)) {
                        throw ValidationException::withMessages(['module_codes' => 'Modul '.$code.' memerlukan '.implode(', ', $missing).'. Centang modul pendukung terlebih dahulu.']);
                    }
                }
                $license = License::lockForUpdate()->firstOrFail();
                $rows = TenantModuleEntitlement::orderBy('id')->lockForUpdate()->get()->keyBy('module_id');
                $before = $rows->where('is_granted', true)->keys()->all();
                foreach ($modules as $module) {
                    $entitlement = $rows->get($module->id) ?? new TenantModuleEntitlement(['module_id' => $module->id, 'is_enabled' => true]);
                    $entitlement->fill(['is_granted' => in_array($module->code, $selected, true), 'granted_by' => $developer->id]);
                    if (array_key_exists($module->code, $data['module_expiries'] ?? [])) {
                        $date = $data['module_expiries'][$module->code];
                        $entitlement->expires_at = $date ? Carbon::parse($date, $tenant->timezone)->endOfDay()->utc() : null;
                    }
                    $entitlement->save();
                }
                if (array_key_exists('access_status', $data)) {
                    $license->update(['status' => $data['access_status'], 'activated_at' => $data['access_status'] === 'active' ? ($license->activated_at ?? now()) : $license->activated_at]);
                }
                if (array_key_exists('expires_at', $data)) {
                    $license->update(['expires_at' => $data['expires_at'] ? Carbon::parse($data['expires_at'], $tenant->timezone)->endOfDay()->utc() : null]);
                }
                AuditLog::create(['actor_id' => $developer->id, 'action' => 'platform.modul_ditetapkan', 'metadata' => ['module_codes' => $selected, 'previous_module_ids' => $before, 'access_status' => $license->status, 'expires_at' => $license->expires_at?->toISOString(), 'module_expiries' => $data['module_expiries'] ?? []]]);
            });
        } finally {
            $this->context->set($previous);
        }
    }
}
