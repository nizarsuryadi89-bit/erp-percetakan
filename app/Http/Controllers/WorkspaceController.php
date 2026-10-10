<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function module(Module $module): Response
    {
        return Inertia::render('foundation/workspace', ['code' => $module->code, 'module' => ['name' => $module->name, 'description' => $module->description, 'dependencies' => [], 'steps' => []]]);
    }

    public function show(string $code): Response
    {
        $module = config('erp.modules.'.$code);
        abort_unless($module, 404);

        return Inertia::render('foundation/workspace', ['code' => $code, 'module' => $module]);
    }
}
