<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouterRequest;
use App\Jobs\SyncRouterStatus;
use App\Models\NetworkZone;
use App\Models\Router;
use App\Models\RouterWireGuardPeer;
use App\Services\MikroTik\MikroTikService;
use App\Services\RouterClientSyncService;
use App\Services\RouterStatusService;
use App\Services\Hotspot\HotspotPortalPackageService;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RouterController extends Controller
{
    public function index(
        RouterStatusService $status
    ): Response {
        $routers = Router::query()
            ->with([
                'zone:id,name,code,service_type',
            ])
            ->latest()
            ->get()
            ->map(
                fn (Router $router): array =>
                    array_merge(
                        $router->toArray(),
                        [
                            'live' =>
                                $status->stored(
                                    $router
                                ),
                        ]
                    )
            );

        return Inertia::render(
            'Routers/Index',
            [
                'routers' => $routers,
            ]
        );
    }

    public function create(
        Request $request
    ): Response {
        $zones =
            $this->routerZoneOptions(
                $request->user()
            );

        return Inertia::render(
            'Routers/Create',
            [
                'zones' =>
                    $zones,

                'selectedZoneId' =>
                    $this->preferredRouterZoneId(
                        $request,
                        $zones
                    ),
            ]
        );
    }

    public function store(
        RouterRequest $request
    ): RedirectResponse {
        $router = Router::create(
            $request->validated()
        );

        /*
         * VPN_FIRST_AUTO_LINK_V2
         *
         * If reseller created the VPN before registering
         * the Router, entering the MikroTik VPN Local IP
         * as Router Host automatically binds the peer.
         */
        $router->loadMissing([
            'zone:id,reseller_id,service_type',
        ]);

        $routerResellerId =
            $request->user()?->reseller_id
            ?: $router->zone?->reseller_id;

        if ($routerResellerId) {
            RouterWireGuardPeer::query()
                ->where(
                    'reseller_id',
                    (int)
                    $routerResellerId
                )
                ->whereNull(
                    'router_id'
                )
                ->where(
                    'client_ip',
                    $router->host
                )
                ->where(
                    'active',
                    true
                )
                ->update([
                    'router_id' =>
                        $router->id,
                ]);
        }

        SyncRouterStatus::dispatch(
            $router->id
        );

        /*
         * HOTSPOT_ROUTER_SETUP_WIZARD_PHASE1_V1
         *
         * MAC routers keep the old workflow.
         * Hotspot routers continue into the guided setup.
         */
        if (
            $router->zone?->service_type
            === 'hotspot'
        ) {
            return redirect()
                ->route(
                    'routers.hotspot-setup',
                    $router
                )
                ->with(
                    'success',
                    'Router saved. Continue with the Hotspot Setup Wizard.'
                );
        }

        return redirect()
            ->route('routers.index')
            ->with(
                'success',
                $router->enabled
                    ? 'Router saved. Background MikroTik status refresh queued. Client synchronization will continue automatically.'
                    : 'Router saved in disabled state.'
            );
    }

    public function show(
        Router $router
    ): RedirectResponse {
        return redirect()
            ->route('routers.index');
    }

    public function edit(
        Request $request,
        Router $router
    ): Response {
        $zones =
            $this->routerZoneOptions(
                $request->user()
            );

        $router->loadMissing([
            'zone:id,name,code,service_type',
        ]);

        return Inertia::render(
            'Routers/Edit',
            [
                'router' =>
                    $router,

                'zones' =>
                    $zones,

                'selectedZoneId' =>
                    $this->preferredRouterZoneId(
                        $request,
                        $zones,
                        (int)
                        $router->zone_id
                    ),
            ]
        );
    }

    public function update(
        RouterRequest $request,
        Router $router
    ): RedirectResponse {
        $data =
            $request->validated();

        /*
         * Blank password keeps the old encrypted
         * MikroTik password.
         */
        if (empty($data['password'])) {
            unset(
                $data['password']
            );
        }

        $router->update(
            $data
        );

        $router->refresh();

        SyncRouterStatus::dispatch(
            $router->id
        );

        return redirect()
            ->route('routers.index')
            ->with(
                'success',
                $router->enabled
                    ? 'Router updated. Background MikroTik status refresh queued. Client synchronization will continue automatically.'
                    : 'Router updated in disabled state.'
            );
    }

    public function sync(
        Router $router
    ): RedirectResponse {
        SyncRouterStatus::dispatch(
            $router->id
        );

        return back()->with(
            'success',
            $router->enabled
                ? 'MikroTik synchronization queued in background. You can continue using the panel.'
                : 'Router is disabled. Background status refresh queued.'
        );
    }

    public function ping(
        Router $router
    ): RedirectResponse {
        try {
            $result = Process::timeout(10)->run([
                'ping',
                '-c',
                '2',
                '-W',
                '2',
                $router->host,
            ]);

            if (!$result->successful()) {
                $error = trim(
                    $result->errorOutput()
                    ?: $result->output()
                );

                return back()->with(
                    'error',
                    'Ping failed for '
                        . $router->host
                        . ($error ? ': ' . $error : '')
                );
            }

            $output = $result->output();
            $averageLatency = null;

            if (
                preg_match(
                    '/=\s*[\d.]+\/([\d.]+)\//',
                    $output,
                    $matches
                )
            ) {
                $averageLatency = $matches[1] . ' ms';
            }

            return back()->with(
                'success',
                'Ping successful: '
                    . $router->host
                    . ($averageLatency
                        ? ' — Average ' . $averageLatency
                        : '')
            );
        } catch (Throwable $exception) {
            return back()->with(
                'error',
                'Ping test failed: '
                    . $exception->getMessage()
            );
        }
    }

    /*
     * HOTSPOT_ROUTER_SETUP_WIZARD_PHASE1_V1
     *
     * Phase 1 is strictly read-only against RouterOS.
     */
    public function hotspotSetup(
        Request $request,
        Router $router,
        MikroTikService $mikrotik
    ): Response {
        $router->loadMissing([
            'zone:id,reseller_id,name,code,service_type',
        ]);

        abort_unless(
            $router->zone
            && $router->zone->service_type
                === 'hotspot',
            404
        );

        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        $resellerId =
            $router->zone->reseller_id
                ? (int)
                    $router
                        ->zone
                        ->reseller_id
                : (
                    $router->reseller_id
                        ? (int)
                            $router
                                ->reseller_id
                        : null
                );

        $superAdmin =
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin();

        if (!$superAdmin) {
            abort_unless(
                $resellerId
                && $user->reseller_id
                && (int)
                    $user->reseller_id
                    === $resellerId,
                403
            );

            if (
                method_exists(
                    $user,
                    'isOperator'
                )
                && $user->isOperator()
            ) {
                abort_unless(
                    $user->zone_id
                    && (int)
                        $user->zone_id
                        === (int)
                            $router
                                ->zone_id,
                    403
                );
            }
        }

        $discovery =
            $router->enabled
                ? $mikrotik
                    ->hotspotSetupDiscovery(
                        $router
                    )
                : [
                    'success' => false,

                    'message' =>
                        'Router is disabled. Enable it before continuing Hotspot setup.',

                    'interfaces' => [],
                    'ethernet_interfaces' => [],

                    'routeros_query_count' => 0,

                    'checked_at' =>
                        now()->toISOString(),
                ];

        return Inertia::render(
            'Routers/HotspotSetup',
            [
                'router' => [
                    'id' =>
                        $router->id,

                    'name' =>
                        $router->name,

                    'host' =>
                        $router->host,

                    'api_port' =>
                        $router->api_port,

                    'use_ssl' =>
                        (bool)
                        $router->use_ssl,

                    'enabled' =>
                        (bool)
                        $router->enabled,

                    'zone_id' =>
                        $router->zone_id,

                    'zone' => [
                        'id' =>
                            $router
                                ->zone
                                ->id,

                        'name' =>
                            $router
                                ->zone
                                ->name,

                        'code' =>
                            $router
                                ->zone
                                ->code,

                        'service_type' =>
                            $router
                                ->zone
                                ->service_type,
                    ],
                ],

                'discovery' =>
                    $discovery,

                'wizardStep' =>
                    max(
                        1,
                        min(
                            9,
                            (int)
                            $request->query(
                                'step',
                                1
                            )
                        )
                    ),

                'activeBridge' =>
                    trim(
                        (string)
                        $request->query(
                            'bridge',
                            ''
                        )
                    ) ?: null,
            ]
        );
    }

    /*
     * HOTSPOT_BRIDGE_SETUP_PHASE2_V2
     */
    public function hotspotSetupBridge(
        Request $request,
        Router $router,
        MikroTikService $mikrotik
    ): RedirectResponse {
        $router->loadMissing([
            'zone:id,reseller_id,name,code,service_type',
        ]);

        abort_unless(
            $router->zone
            && $router->zone->service_type
                === 'hotspot',
            404
        );

        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        $resellerId =
            $router->zone->reseller_id
                ? (int)
                    $router
                        ->zone
                        ->reseller_id
                : (
                    $router->reseller_id
                        ? (int)
                            $router
                                ->reseller_id
                        : null
                );

        $superAdmin =
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin();

        if (!$superAdmin) {
            abort_unless(
                $resellerId
                && $user->reseller_id
                && (int)
                    $user->reseller_id
                    === $resellerId,
                403
            );

            if (
                method_exists(
                    $user,
                    'isOperator'
                )
                && $user->isOperator()
            ) {
                abort_unless(
                    $user->zone_id
                    && (int)
                        $user->zone_id
                        === (int)
                            $router
                                ->zone_id,
                    403
                );
            }
        }

        $data =
            $request->validate([
                'mode' => [
                    'required',
                    'string',
                    'in:existing,new',
                ],

                'bridge_name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'ports' => [
                    'required',
                    'array',
                    'min:1',
                    'max:64',
                ],

                'ports.*' => [
                    'required',
                    'string',
                    'max:100',
                    'distinct',
                ],
            ]);

        try {
            $result =
                $mikrotik
                    ->applyHotspotBridge(
                        $router,
                        $data['mode'],
                        $data['bridge_name'],
                        $data['ports']
                    );

        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $exception
                        ->getMessage()
                );
        }

        return redirect()
            ->route(
                'routers.hotspot-setup',
                [
                    'router' =>
                        $router,

                    'step' =>
                        3,

                    'bridge' =>
                        $result[
                            'bridge'
                        ],
                ]
            )
            ->with(
                'success',
                'Hotspot bridge is ready. Continue with Gateway & Subnet.'
            );
    }

    /*
     * MAIN_HOTSPOT_PORTAL_PACKAGE_V1
     *
     * Generates a reseller-branded MikroTik Hotspot
     * portal ZIP. Download preparation adds only
     * the MikroPanel walled-garden host to RouterOS.
     */
    public function downloadHotspotPortal(
        Request $request,
        Router $router,
        HotspotPortalPackageService $packages,
        HotspotRouterService $hotspotRouter
    ): BinaryFileResponse|RedirectResponse {
        $router->loadMissing([
            'zone:id,reseller_id,service_type',
        ]);

        abort_unless(
            $router->zone
            && $router->zone->service_type
                === 'hotspot',
            404
        );

        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        $resellerId =
            $router->zone->reseller_id
                ? (int)
                    $router
                        ->zone
                        ->reseller_id
                : null;

        $superAdmin =
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin();

        /*
         * Normal Company users may only download
         * packages for their own reseller zone.
         *
         * Super Admin may support any visible router.
         */
        if (!$superAdmin) {
            abort_unless(
                $resellerId
                && $user->reseller_id
                && (int)
                    $user->reseller_id
                    === $resellerId,
                403
            );
        }

        /*
         * MAIN_HOTSPOT_PORTAL_WALLED_GARDEN_V1
         *
         * The public MAC-reset API must be reachable
         * before Hotspot authentication.
         */
        $portalHost =
            parse_url(
                (string)
                config('app.url'),
                PHP_URL_HOST
            );

        if (
            !is_string($portalHost)
            || trim($portalHost) === ''
        ) {
            return back()->with(
                'error',
                'Portal host configuration is invalid.'
            );
        }

        try {
            $hotspotRouter
                ->ensurePortalHostAccess(
                    $router,
                    $portalHost
                );

        } catch (Throwable $exception) {
            return back()->with(
                'error',
                'Hotspot portal package was not downloaded because the router could not prepare MAC Reset access: '
                . $exception->getMessage()
            );
        }

        $package =
            $packages->buildForRouter(
                $router
            );

        return response()
            ->download(
                $package['path'],
                $package['filename'],
                [
                    'Content-Type' =>
                        'application/zip',

                    'Cache-Control' =>
                        'private, no-store, max-age=0',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function destroy(
        Router $router
    ): RedirectResponse {
        $router->delete();

        return back()->with(
            'success',
            'Router deleted.'
        );
    }

    /*
     * ROUTER_FORM_SERVICE_ZONE_OPTIONS_V2
     *
     * Router service is selected by Network Zone.
     * MAC-zone routers participate in MAC/IP fanout.
     * Hotspot-zone routers are used by Hotspot discovery.
     */
    private function routerZoneOptions(
        $user
    ) {
        $query =
            NetworkZone::query()
                ->where(
                    'enabled',
                    true
                )
                ->whereIn(
                    'service_type',
                    [
                        'mac',
                        'hotspot',
                    ]
                );

        if (
            $user
            && method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin()
        ) {
            // All enabled MAC zones.

        } elseif (
            $user
            && $user->reseller_id
        ) {
            $query->where(
                'reseller_id',
                (int)
                $user->reseller_id
            );

            if (
                method_exists(
                    $user,
                    'isOperator'
                )
                && $user->isOperator()
            ) {
                $query->whereKey(
                    (int)
                    $user->zone_id
                );
            }

        } else {
            $query->whereNull(
                'reseller_id'
            );
        }

        return $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'service_type',
            ]);
    }

    private function preferredRouterZoneId(
        Request $request,
        $zones,
        ?int $currentZoneId = null
    ): ?int {
        if (
            $currentZoneId
            && $zones->contains(
                'id',
                $currentZoneId
            )
        ) {
            return $currentZoneId;
        }

        $user =
            $request->user();

        if (
            $user
            && method_exists(
                $user,
                'isOperator'
            )
            && $user->isOperator()
            && $user->zone_id
            && $zones->contains(
                'id',
                (int)
                $user->zone_id
            )
        ) {
            return (int)
                $user->zone_id;
        }

        $sessionZoneId =
            (int)
            $request
                ->session()
                ->get(
                    'network_zone_id',
                    0
                );

        if (
            $sessionZoneId
            && $zones->contains(
                'id',
                $sessionZoneId
            )
        ) {
            return $sessionZoneId;
        }

        if (
            $zones->count() === 1
        ) {
            return (int)
                $zones
                    ->first()
                    ->id;
        }

        return null;
    }


    private function saveLiveStatus(
        Router $router,
        array $live
    ): void {
        $columns = Schema::getColumnListing('routers');

        $values = [];

        if (in_array('connected', $columns, true)) {
            $values['connected'] = (bool) (
                $live['success'] ?? false
            );
        }

        if (in_array('last_checked_at', $columns, true)) {
            $values['last_checked_at'] = now();
        }

        if (in_array('last_error', $columns, true)) {
            $values['last_error'] = $live['success']
                ? null
                : ($live['message'] ?? 'Unknown error');
        }

        if ($live['success'] ?? false) {
            if (in_array('last_seen_at', $columns, true)) {
                $values['last_seen_at'] = now();
            }

            $fieldMap = [
                'identity' => 'identity',
                'routeros_version' => 'version',
                'board_name' => 'board_name',
                'uptime' => 'uptime',
                'cpu_load' => 'cpu_load',
                'free_memory' => 'free_memory',
                'total_memory' => 'total_memory',
                'dhcp_leases_count' =>
                    'dhcp_leases_count',
                'arp_entries_count' =>
                    'arp_entries_count',
                'simple_queues_count' =>
                    'simple_queues_count',
            ];

            foreach ($fieldMap as $column => $liveKey) {
                if (
                    in_array($column, $columns, true)
                    && array_key_exists($liveKey, $live)
                ) {
                    $values[$column] = $live[$liveKey];
                }
            }
        }

        if (!empty($values)) {
            $router->forceFill($values);
            $router->saveQuietly();
        }
    }
}
