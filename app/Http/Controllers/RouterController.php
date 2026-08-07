<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouterRequest;
use App\Models\Router;
use App\Services\MikroTik\MikroTikService;
use App\Services\RouterClientSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RouterController extends Controller
{
    public function index(
        MikroTikService $mikrotik
    ): Response {
        $routers = Router::query()
            ->latest()
            ->get()
            ->map(function (Router $router) use ($mikrotik) {
                if (!$router->enabled) {
                    $live = [
                        'success' => false,
                        'disabled' => true,
                        'message' => 'Router is disabled.',
                        'latency_ms' => null,
                        'checked_at' => now()->toISOString(),
                    ];
                } else {
                    $live = $mikrotik->inspect($router);

                    $this->saveLiveStatus(
                        $router,
                        $live
                    );
                }

                return array_merge(
                    $router->fresh()->toArray(),
                    [
                        'live' => $live,
                    ]
                );
            });

        return Inertia::render('Routers/Index', [
            'routers' => $routers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Routers/Create');
    }

    public function store(
        RouterRequest $request,
        MikroTikService $mikrotik,
        RouterClientSyncService $clientSync
    ): RedirectResponse {
        $data = $request->validated();

        $router = Router::create(
            $data
        );

        $live = $mikrotik->inspect(
            $router
        );

        $this->saveLiveStatus(
            $router,
            $live
        );

        $sync = $clientSync
            ->emptyResult();

        if ($router->enabled) {
            if ($live['success'] ?? false) {
                /*
                 * Immediate first convergence.
                 * No scheduler wait required.
                 */
                $sync = $clientSync
                    ->syncAll(
                        $router
                    );

            } else {
                /*
                 * Router is offline/unreachable.
                 * Seed retryable failed bindings.
                 */
                $sync = $clientSync
                    ->markRouterFailed(
                        $router,
                        $live['message']
                            ?? 'MikroTik API connection failed.'
                    );
            }
        }

        if (!($live['success'] ?? false)) {
            $message =
                'Router saved, but MikroTik API connection failed: '
                . (
                    $live['message']
                    ?? 'Unknown error'
                )
                . '. Client sync will retry automatically.';

            $flashType = 'error';

        } elseif ($sync['failed'] > 0) {
            $message =
                'Router connected. '
                . $sync['synced']
                . ' client(s) synced, '
                . $sync['failed']
                . ' failed and will retry automatically.';

            $flashType = 'error';

        } else {
            $message =
                'Router added successfully. '
                . $sync['synced']
                . ' existing client(s) synchronized.';

            $flashType = 'success';
        }

        return redirect()
            ->route('routers.index')
            ->with(
                $flashType,
                $message
            );
    }

    public function show(
        Router $router
    ): RedirectResponse {
        return redirect()
            ->route('routers.index');
    }

    public function edit(
        Router $router
    ): Response {
        return Inertia::render('Routers/Edit', [
            'router' => $router,
        ]);
    }

    public function update(
        RouterRequest $request,
        Router $router,
        MikroTikService $mikrotik,
        RouterClientSyncService $clientSync
    ): RedirectResponse {
        $data = $request->validated();

        /*
         * Blank password keeps the old
         * encrypted MikroTik password.
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

        $live = $mikrotik->inspect(
            $router
        );

        $this->saveLiveStatus(
            $router,
            $live
        );

        $sync = $clientSync
            ->emptyResult();

        if ($router->enabled) {
            if ($live['success'] ?? false) {
                /*
                 * Force convergence after any
                 * credential/interface/DHCP
                 * mapping change.
                 */
                $sync = $clientSync
                    ->syncAll(
                        $router
                    );

            } else {
                $sync = $clientSync
                    ->markRouterFailed(
                        $router,
                        $live['message']
                            ?? 'MikroTik API connection failed.'
                    );
            }
        }

        if (!($live['success'] ?? false)) {
            $message =
                'Router updated, but MikroTik connection failed: '
                . (
                    $live['message']
                    ?? 'Unknown error'
                )
                . '. Client sync will retry automatically.';

            $flashType = 'error';

        } elseif ($sync['failed'] > 0) {
            $message =
                'Router updated. '
                . $sync['synced']
                . ' client(s) synced, '
                . $sync['failed']
                . ' failed and will retry automatically.';

            $flashType = 'error';

        } else {
            $message =
                'Router updated successfully. '
                . $sync['synced']
                . ' client(s) synchronized.';

            $flashType = 'success';
        }

        return redirect()
            ->route('routers.index')
            ->with(
                $flashType,
                $message
            );
    }

    public function sync(
        Router $router,
        MikroTikService $mikrotik,
        RouterClientSyncService $clientSync
    ): RedirectResponse {
        $live = $mikrotik->inspect(
            $router
        );

        $this->saveLiveStatus(
            $router,
            $live
        );

        if (!($live['success'] ?? false)) {
            if ($router->enabled) {
                $clientSync
                    ->markRouterFailed(
                        $router,
                        $live['message']
                            ?? 'MikroTik API connection failed.'
                    );
            }

            return back()->with(
                'error',
                'MikroTik sync failed: '
                . (
                    $live['message']
                    ?? 'Unknown error'
                )
            );
        }

        $sync = $router->enabled
            ? $clientSync->syncAll(
                $router
            )
            : $clientSync
                ->emptyResult();

        if ($sync['failed'] > 0) {
            return back()->with(
                'error',
                'Router connected. '
                . $sync['synced']
                . ' client(s) synced, '
                . $sync['failed']
                . ' failed and will retry automatically.'
            );
        }

        return back()->with(
            'success',
            'MikroTik sync completed. '
            . $sync['synced']
            . ' client(s) synchronized. Identity: '
            . (
                $live['identity']
                ?? $router->name
            )
            . ', RouterOS: '
            . (
                $live['version']
                ?? 'Unknown'
            )
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
