<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminPanelOnly
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user =
            $request->user();

        /*
         * SUPER_ADMIN_PANEL_ONLY_V1
         *
         * Guests and normal Company users keep their
         * existing public/panel access.
         */
        if (
            !$user
            || !$user->isSuperAdmin()
        ) {
            return $next(
                $request
            );
        }

        $routeName =
            $request
                ->route()
                ?->getName();

        /*
         * Super Admin may use only:
         *
         * - dedicated Super Admin panel routes
         * - logout
         *
         * Public website, normal dashboard,
         * Company panel, MAC POS, Accounting,
         * Settings and every other panel route
         * are redirected back to Super Admin.
         */
        if (
            $routeName === 'logout'
            || (
                $routeName !== null
                && str_starts_with(
                    $routeName,
                    'superadmin.'
                )
            )
        ) {
            return $next(
                $request
            );
        }

        return redirect()
            ->route(
                'superadmin.dashboard'
            );
    }
}
