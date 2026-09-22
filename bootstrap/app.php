<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            /* SUPER_ADMIN_PANEL_ONLY_MIDDLEWARE_V1 */
            \App\Http\Middleware\SuperAdminPanelOnly::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ShareUnifiedFinance::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
                    \App\Http\Middleware\EnforceResellerAccess::class,
]);

        /*
         * MAIN_HOTSPOT_MAC_RESET_CSRF_V1
         *
         * Captive portal HTML is served by MikroTik
         * and therefore has no Laravel CSRF session.
         */
        $middleware->validateCsrfTokens(
            except: [
                'hotspot-portal/mac-reset/*',
            ]
        );

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
