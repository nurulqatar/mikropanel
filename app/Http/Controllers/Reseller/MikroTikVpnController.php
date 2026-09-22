<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\RouterWireGuardPeer;
use App\Services\RouterWireGuardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MikroTikVpnController extends Controller
{
    /*
     * RESELLER_VPN_FIRST_FLOW_V2
     */

    public function index(
        Request $request,
        RouterWireGuardService $wireguard
    ): Response {
        $resellerId =
            $this->resellerId(
                $request
            );

        $peers =
            RouterWireGuardPeer::query()
                ->where(
                    'reseller_id',
                    $resellerId
                )
                ->with([
                    'router:id,name,host',
                ])
                ->latest()
                ->get()
                ->map(
                    fn (
                        RouterWireGuardPeer $peer
                    ): array =>
                        $wireguard
                            ->standaloneSnapshot(
                                $peer
                            )
                )
                ->values();

        return Inertia::render(
            'Reseller/MikroTikVpn',
            [
                'peers' =>
                    $peers,

                'server' => [
                    'tunnel_ip' =>
                        config(
                            'router_wireguard.server_tunnel_ip'
                        ),

                    'endpoint' =>
                        config(
                            'router_wireguard.endpoint_host'
                        )
                        . ':'
                        . config(
                            'router_wireguard.endpoint_port'
                        ),

                    'pool' =>
                        config(
                            'router_wireguard.client_prefix'
                        )
                        . config(
                            'router_wireguard.client_start'
                        )
                        . '-'
                        . config(
                            'router_wireguard.client_end'
                        ),
                ],

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


    public function store(
        Request $request,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $resellerId =
            $this->resellerId(
                $request
            );

        $data =
            $request->validate([
                'label' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
            ]);

        try {
            $peer =
                $wireguard
                    ->createStandalone(
                        $resellerId,
                        $request->user()?->id,
                        $data['label']
                            ?? null
                    );

            return back()->with(
                'success',
                'VPN created. MikroTik VPN Local IP: '
                . $peer->client_ip
                . '. Copy the command, paste it into MikroTik, then use this IP when adding the router.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }


    public function check(
        Request $request,
        RouterWireGuardPeer $peer,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertPeer(
            $request,
            $peer
        );

        try {
            $status =
                $wireguard
                    ->refreshStandaloneStatus(
                        $peer
                    );

            return back()->with(
                'success',
                $status['connected']
                    ? 'WireGuard connected. You can now add the MikroTik using VPN Local IP '
                        . $peer->client_ip
                        . '.'
                    : 'No recent WireGuard handshake yet. Paste the command into MikroTik and check again.'
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
        RouterWireGuardPeer $peer,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertPeer(
            $request,
            $peer
        );

        try {
            $wireguard
                ->rotateStandalone(
                    $peer
                );

            return back()->with(
                'success',
                'VPN regenerated. Paste the NEW command into MikroTik.'
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
        RouterWireGuardPeer $peer,
        RouterWireGuardService $wireguard
    ): RedirectResponse {
        $this->assertPeer(
            $request,
            $peer
        );

        try {
            $wireguard
                ->revokeStandalone(
                    $peer
                );

            return back()->with(
                'success',
                'VPN revoked.'
            );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }


    private function resellerId(
        Request $request
    ): int {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->reseller_id,
            403
        );

        return (int)
            $user->reseller_id;
    }


    private function assertPeer(
        Request $request,
        RouterWireGuardPeer $peer
    ): void {
        abort_unless(
            (int)
            $peer->reseller_id
            === $this->resellerId(
                $request
            ),
            403
        );
    }
}
