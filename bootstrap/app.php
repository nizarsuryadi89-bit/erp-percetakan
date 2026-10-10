<?php

use App\Http\Middleware\EnsureCashierReady;
use App\Http\Middleware\EnsureDeskprintReady;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsurePlatformAdministrator;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'deskprint' => EnsureDeskprintReady::class,
            'cashier' => EnsureCashierReady::class,
            'tenant' => ResolveTenant::class,
            'module' => EnsureModuleAccess::class,
            'platform' => EnsurePlatformAdministrator::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureCashierReady::class);
        $middleware->prependToPriorityList(EnsureCashierReady::class, EnsureDeskprintReady::class);
        $middleware->prependToPriorityList(EnsureDeskprintReady::class, EnsureModuleAccess::class);
        $middleware->prependToPriorityList(EnsureModuleAccess::class, ResolveTenant::class);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            if (request()->routeIs('invoices.*', 'invoice-payments.*', 'payment-methods.*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
