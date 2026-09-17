<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouterRequest;
use App\Jobs\SyncRouterStatus;
use App\Models\NetworkZone;
use App\Models\Router;
use App\Services\MikroTik\MikroTikService;
use App\Services\RouterClientSyncService;
use App\Services\RouterStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
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

        SyncRouterStatus::dispatch(
            $router->id
        );

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
