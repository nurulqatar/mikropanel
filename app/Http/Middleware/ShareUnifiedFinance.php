<?php

namespace App\Http\Middleware;

use App\Services\UnifiedFinanceService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareUnifiedFinance
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $route =
            $request
                ->route()
                ?->getName();

        if (
            $request->user()
            && (
                $route === 'dashboard'
                || $route ===
                    'accounting.index'
                || str_starts_with(
                    (string) $route,
                    'hotspot.'
                )
            )
        ) {
            Inertia::share(
                'unifiedFinance',
                fn (): array =>
                    app(
                        UnifiedFinanceService::class
                    )->summary()
            );
        }

        return $next(
            $request
        );
    }
}
