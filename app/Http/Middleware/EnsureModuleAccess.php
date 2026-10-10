<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Support\ModuleAccess;
use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(private ModuleRegistry $registry, private ModuleAccess $access) {}

    public function handle(Request $request, Closure $next, ?string $code = null): Response
    {
        $boundModule = $request->route('module');
        $code ??= $boundModule instanceof Module ? $boundModule->code : (string) $boundModule;
        abort_unless($this->registry->isReady(), 503, 'Akses perusahaan sedang disiapkan. Hubungi pengelola.');
        $module = $this->registry->resolve($code);
        abort_unless($module, 404);
        abort_unless($this->access->allows($request->user(), $module->code), 403, 'Modul tidak tersedia untuk akun atau perusahaan ini.');

        return $next($request);
    }
}
