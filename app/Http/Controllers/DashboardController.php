<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\License;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context, ModuleRegistry $registry): Response
    {
        return Inertia::render('dashboard', ['license' => License::first(), 'licenseUsable' => License::first()?->isUsable() ?? false, 'accessReady' => $registry->isReady(), 'branchCount' => Branch::count(), 'userCount' => $context->tenant()->users()->wherePivot('is_active', true)->count(), 'recentActivity' => AuditLog::orderByDesc('id')->limit(5)->get(['id', 'action', 'created_at']), 'catalog' => $registry->tenantCatalog()]);
    }
}
