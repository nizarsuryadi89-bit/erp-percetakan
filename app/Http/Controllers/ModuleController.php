<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\License;
use App\Models\Tenant;
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
        abort_unless($registry->isReady(), 503, 'Akses perusahaan sedang disiapkan. Hubungi pengelola.');
        $entitlements = TenantModuleEntitlement::with('module')->where('is_granted', true)->orderBy('id')->get();

        return Inertia::render('foundation/modules', ['license' => License::first(), 'catalog' => $registry->tenantCatalog(), 'entitlements' => $entitlements->map(fn (TenantModuleEntitlement $row): array => ['code' => $row->module->code, 'is_granted' => true, 'is_enabled' => $row->is_enabled, 'is_usable' => $row->isUsable(), 'expires_at' => $row->expires_at?->toISOString()])]);
    }

    public function update(Request $request, string $code, ModuleRegistry $registry, TenantContext $context): RedirectResponse
    {
        Gate::authorize('modul.manage');
        abort_unless($registry->isReady(), 503, 'Akses perusahaan sedang disiapkan. Hubungi pengelola.');
        $module = $registry->resolve($code);
        abort_unless($module, 404);
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $registry, $context, $module, $code, $data): void {
            Tenant::whereKey($context->id())->lockForUpdate()->firstOrFail();
            License::lockForUpdate()->firstOrFail();
            $entitlement = TenantModuleEntitlement::where('module_id', $module->id)->lockForUpdate()->first();
            abort_unless($entitlement?->is_granted, 403, 'Hak modul ini hanya dapat diberikan developer.');
            $code = $module->code;
            if ($data['is_enabled'] && (! $module->is_active || ! $entitlement->isWithinAccessWindow())) {
                throw ValidationException::withMessages(['is_enabled' => 'Masa akses modul belum berlaku atau telah berakhir. Hubungi developer.']);
            }
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
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'modul.diperbarui', 'metadata' => ['code' => $code, ...$data]]);
        });

        return back()->with('success', 'Pengaturan modul diperbarui.');
    }
}
