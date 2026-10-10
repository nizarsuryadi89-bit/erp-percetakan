<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\License;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantModuleEntitlement;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(ModuleRegistry $registry): Response
    {
        Gate::authorize('modul.manage');
        $entitlements = TenantModuleEntitlement::with('module')->where('is_granted', true)->orderBy('id')->get();

        return Inertia::render('foundation/modules', ['license' => License::first(), 'catalog' => $registry->tenantCatalog(), 'entitlements' => $entitlements->map(fn (TenantModuleEntitlement $row): array => ['code' => $row->module->code, 'is_enabled' => $row->is_enabled, 'is_usable' => $row->isUsable()])]);
    }

    public function update(Request $request, string $code, ModuleRegistry $registry, TenantContext $context): RedirectResponse
    {
        Gate::authorize('modul.manage');
        $module = $registry->resolve($code);
        abort_unless($module, 404);
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $registry, $context, $module, $code, $data): void {
            Tenant::whereKey($context->id())->lockForUpdate()->firstOrFail();
            License::lockForUpdate()->firstOrFail();
            $entitlement = TenantModuleEntitlement::where('module_id', $module->id)->lockForUpdate()->first();
            abort_unless($entitlement?->is_granted, 403, 'Hak modul ini hanya dapat diberikan developer.');
            if ($code === $module->code) {
                if ($data['is_enabled']) {
                    $available = $registry->availableCodes();
                    foreach (array_diff($registry->requiredCodes($code), [$code]) as $dependency) {
                        if (! in_array($dependency, $available, true)) {
                            throw ValidationException::withMessages(['is_enabled' => 'Aktifkan modul pendukung '.$dependency.' terlebih dahulu.']);
                        }
                    }
                } else {
                    $enabled = TenantModuleEntitlement::with('module')->where('is_granted', true)->where('is_enabled', true)->get();
                    foreach ($enabled as $other) {
                        if ($other->module->code !== $code && in_array($code, $registry->requiredCodes($other->module->code), true)) {
                            throw ValidationException::withMessages(['is_enabled' => 'Nonaktifkan '.$other->module->name.' terlebih dahulu.']);
                        }
                    }
                }
                $entitlement->update($data);
            } else {
                $catalog = config('erp.modules');
                abort_unless(isset($catalog[$code]), 404);
                $rows = TenantModule::orderBy('id')->lockForUpdate()->get();
                $enabled = $rows->where('is_enabled', true)->pluck('code')->all();
                if ($data['is_enabled']) {
                    foreach ($catalog[$code]['dependencies'] as $dependency) {
                        if (! in_array($dependency, $enabled, true)) {
                            throw ValidationException::withMessages(['is_enabled' => 'Aktifkan '.$catalog[$dependency]['name'].' terlebih dahulu.']);
                        }
                    }
                } else {
                    foreach ($enabled as $dependent) {
                        if (in_array($code, $catalog[$dependent]['dependencies'], true)) {
                            throw ValidationException::withMessages(['is_enabled' => 'Nonaktifkan '.$catalog[$dependent]['name'].' terlebih dahulu.']);
                        }
                    }
                }
                $row = $rows->firstWhere('code', $code);
                abort_unless($row, 404);
                $row->update($data);
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'modul.diperbarui', 'metadata' => ['code' => $code, ...$data]]);
        });

        return back()->with('success', 'Pengaturan modul diperbarui.');
    }
}
