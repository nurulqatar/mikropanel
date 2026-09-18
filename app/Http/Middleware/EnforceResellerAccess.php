<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Services\Reseller\ResellerUsageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceResellerAccess
{
    public function __construct(
        private ResellerUsageService $usage
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user =
            $request->user();

        if (
            !$user
            || !$user->isResellerUser()
        ) {
            return $next(
                $request
            );
        }

        if (!$user->is_active) {
            Auth::logout();

            $request
                ->session()
                ->invalidate();

            $request
                ->session()
                ->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'Your reseller account is disabled.',
                ]);
        }

        $routeName =
            $request
                ->route()
                ?->getName();

        if ($routeName === 'logout') {
            return $next(
                $request
            );
        }

        /*
         * PUBLIC_WEBSITE_RESELLER_BYPASS
         *
         * Public marketing, pricing, legal and reseller
         * registration/status pages must remain accessible
         * even when the browser already has a reseller
         * session. These are not reseller-panel actions.
         */
        if (
            $routeName
            && str_starts_with(
                $routeName,
                'website.'
            )
        ) {
            return $next(
                $request
            );
        }

        /*
         * PUBLIC_AUTH_RESELLER_BYPASS
         *
         * Login and password-recovery screens are public
         * authentication endpoints, not reseller-panel actions.
         */
        if (
            in_array(
                $routeName,
                [
                    'login',
                    'password.request',
                    'password.email',
                    'password.reset',
                    'password.store',
                ],
                true
            )
        ) {
            return $next(
                $request
            );
        }


        if (
            $routeName === 'dashboard'
        ) {
            return redirect()
                ->route(
                    'reseller.dashboard'
                );
        }

        if (
            !$this->routeAllowed(
                $routeName
            )
        ) {
            abort(
                403,
                'This section is not available to reseller accounts.'
            );
        }

        $reseller =
            Reseller::query()
                ->find(
                    $user->reseller_id
                );

        abort_unless(
            $reseller,
            403,
            'Reseller account not found.'
        );

        /*
         * Dashboard stays available so the
         * reseller can see plan/expiry status.
         */
        if (
            $routeName
            === 'reseller.dashboard'
        ) {
            return $next(
                $request
            );
        }

        if (
            $reseller->status
            !== 'active'
        ) {
            abort(
                403,
                'Reseller account is suspended.'
            );
        }

        /*
         * RESELLER_SUBSCRIPTION_UPGRADE_BYPASS_V1
         *
         * Active reseller owners may buy a higher
         * package from wallet even after expiry.
         */
        if (
            $routeName
            === 'reseller.subscription.upgrade'
            && $user->isResellerOwner()
        ) {
            return $next(
                $request
            );
        }

        if (
            $this->usage
                ->subscriptionIsUsable(
                    $reseller
                )
        ) {
            return $next(
                $request
            );
        }

        return match (
            $reseller->expiry_mode
        ) {
            'read_only' =>
                $this->readOnly(
                    $request,
                    $next
                ),

            'block_new_clients' =>
                $this->blockNewClients(
                    $request,
                    $next,
                    $routeName
                ),

            'panel_lock',
            'full_suspend' =>
                abort(
                    403,
                    'Reseller subscription has expired.'
                ),

            default =>
                abort(
                    403,
                    'Reseller subscription is unavailable.'
                ),
        };
    }

    private function readOnly(
        Request $request,
        Closure $next
    ): Response {
        if (
            in_array(
                $request->method(),
                [
                    'GET',
                    'HEAD',
                    'OPTIONS',
                ],
                true
            )
        ) {
            return $next(
                $request
            );
        }

        abort(
            403,
            'Subscription expired. Panel is read-only.'
        );
    }

    private function blockNewClients(
        Request $request,
        Closure $next,
        ?string $routeName
    ): Response {
        if (
            in_array(
                $routeName,
                [
                    'clients.create',
                    'clients.store',
                ],
                true
            )
        ) {
            abort(
                403,
                'Subscription expired. New client creation is blocked.'
            );
        }

        return $next(
            $request
        );
    }

    private function routeAllowed(
        ?string $routeName
    ): bool {
        if (!$routeName) {
            return true;
        }

        if (
            in_array(
                $routeName,
                [
                    'dashboard',
                    'logout',
                    'profile.edit',
                    'profile.update',
                ],
                true
            )
        ) {
            return true;
        }

        foreach ([
            'reseller.*',
            'clients.*',
            'routers.*',
            'packages.*',
            'ip-ranges.*',
            'invoices.*',
            'payments.*',
            'expenses.*',
            'accounting.*',
            'hotspot.*',
        ] as $pattern) {
            if (
                Str::is(
                    $pattern,
                    $routeName
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
