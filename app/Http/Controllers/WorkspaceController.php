<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Support\ModuleRegistry;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function module(Module $module, ModuleRegistry $registry): Response
    {
        return Inertia::render('foundation/workspace', ['code' => $module->code, 'module' => $registry->tenantCatalog()[$module->code]]);
    }

    public function show(string $code, ModuleRegistry $registry): Response
    {
        $module = $registry->resolve($code);
        abort_unless($module, 404);

        return $this->module($module, $registry);
    }
}
