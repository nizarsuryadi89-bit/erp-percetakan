<?php

namespace App\Http\Middleware;

use App\Models\License;
use App\Models\Module;
use App\Models\TenantModule;
use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(private ModuleRegistry $registry) {}

    public function handle(Request $request, Closure $next, ?string $code = null): Response
    {
        $boundModule = $request->route('module');
        $code ??= $boundModule instanceof Module ? $boundModule->code : (string) $boundModule;
        $module = $this->registry->resolve($code);
        abort_unless($module, 404);
        abort_unless(License::first()?->isUsable(), 403, 'Akses perusahaan belum aktif atau sudah berakhir. Hubungi developer.');
        abort_unless(in_array($module->code, $this->registry->availableCodes(), true), 403, 'Modul belum diberikan developer, dinonaktifkan, atau masa aksesnya berakhir.');
        $catalog = config('erp.modules');
        if (isset($catalog[$code])) {
            $enabled = TenantModule::where('is_enabled', true)->pluck('code')->all();
            foreach ([$code, ...$catalog[$code]['dependencies']] as $required) {
                abort_unless(in_array($required, $enabled, true), 403, 'Menu atau data pendukung belum diaktifkan.');
            }
        }
        abort_unless($request->user()->can($code.'.view'), 403, 'Anda tidak memiliki hak akses modul ini.');

        return $next($request);
    }
}
