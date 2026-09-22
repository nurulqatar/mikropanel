<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRouterStatus;
use App\Models\Router;
use App\Services\RouterWireGuardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RouterWireGuardController extends Controller
{
    /*
     * RESELLER_ROUTER_WIREGUARD_V1
     */

    public function show(
        Request $request,
        Router $router,
        RouterWireGuardService $wireguard
    ): Response {
        $this->assertAccess(
            $request,
            $router
        );

        $snapshot =
            $wireguard->snapshot(
                $router
            );

        return Inertia::render(
            'Routers/WireGuard',
            [
                'mikrotik' => [
                    'id' =>
                        $router->id,

                    'name' =>
                        $router->name,

                    'host' =>
                        $router->host,

                    'api_port' =>
                        $router->api_port,

                    'use_ssl' =>
                        $router->use_ssl,
                ],

                ...$snapshot,

                'flash' => [
                    'success' =>
                        session(
                            'success'
                        ),

                    'error' =>
                        session(
                            'error'
                        ),
                ],
            ]
        );
    }

    public function create(
        Request $request,
        Router $router,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertAccess(
            $request,
            $router
        );

        try {
            $wireguard->create(
                $router
            );

            return back()->with(
                'success',
                'WireGuard VPN created. Copy the MikroTik command and paste it into the router terminal.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }

    public function refresh(
        Request $request,
        Router $router,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertAccess(
            $request,
            $router
        );

        try {
            $status =
                $wireguard
                    ->refreshStatus(
                        $router
                    );

            return back()->with(
                'success',
                $status['connected']
                    ? 'WireGuard is connected and the handshake is fresh.'
                    : 'WireGuard peer exists, but no recent handshake was found.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }

    public function rotate(
        Request $request,
        Router $router,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertAccess(
            $request,
            $router
        );

        try {
            $wireguard->rotate(
                $router
            );

            return back()->with(
                'success',
                'WireGuard keys regenerated. Copy and paste the NEW MikroTik command.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }

    public function revoke(
        Request $request,
        Router $router,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertAccess(
            $request,
            $router
        );

        try {
            $wireguard->revoke(
                $router
            );

            SyncRouterStatus::dispatch(
                $router->id
            );

            return back()->with(
                'success',
                'WireGuard VPN revoked.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }

    public function activateHost(
        Request $request,
        Router $router,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertAccess(
            $request,
            $router
        );

        try {
            $wireguard
                ->activateVpnHost(
                    $router
                );

            SyncRouterStatus::dispatch(
                $router->id
            );

            return back()->with(
                'success',
                'MikroTik API host now uses the WireGuard tunnel IP.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }

    private function assertAccess(
        Request $request,
        Router $router
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        if (
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin()
        ) {
            return;
        }

        $router->loadMissing(
            'zone:id,reseller_id'
        );

        $routerResellerId =
            $router->getAttribute(
                'reseller_id'
            )
            ?: $router->zone
                ?->reseller_id;

        abort_unless(
            $user->reseller_id
            && $routerResellerId
            && (int)
                $user->reseller_id
                === (int)
                    $routerResellerId,
            403
        );
    }
}
