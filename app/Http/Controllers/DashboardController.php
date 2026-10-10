<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\License;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context): Response
    {
        return Inertia::render('dashboard', ['license' => License::first(), 'branchCount' => Branch::count(), 'userCount' => $context->tenant()->users()->wherePivot('is_active', true)->count(), 'recentActivity' => AuditLog::orderByDesc('id')->limit(5)->get(['id', 'action', 'created_at']), 'catalog' => config('erp.modules')]);
    }
}
