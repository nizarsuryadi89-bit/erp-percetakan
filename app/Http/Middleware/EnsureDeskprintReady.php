<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeskprintReady
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['branch_user', 'customers', 'print_orders', 'print_order_items', 'print_order_events', 'deskprint_requests'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Deskprint sedang disiapkan. Hubungi pengelola untuk penerapan migration.');
        }
        abort_unless(Schema::hasColumn('branches', 'spk_sequence'), 503, 'Penomoran SPK sedang disiapkan.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
