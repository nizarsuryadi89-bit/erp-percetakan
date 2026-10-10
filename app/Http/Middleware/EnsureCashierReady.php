<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCashierReady
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['invoices', 'invoice_payments', 'invoice_events', 'payment_methods', 'cashier_requests'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Kasir sedang disiapkan. Hubungi pengelola untuk penerapan migration.');
        }
        abort_unless(Schema::hasColumn('branches', 'invoice_sequence'), 503, 'Penomoran invoice sedang disiapkan.');
        abort_unless($request->isSecure() || in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true), 503, 'Kasir memerlukan HTTPS agar history invoice dapat dienkripsi. Gunakan HTTPS untuk domain aplikasi; HTTP hanya didukung pada localhost.');
        Inertia::encryptHistory();
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
