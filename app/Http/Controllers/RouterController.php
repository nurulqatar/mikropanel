<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouterRequest;
use App\Models\Router;
use App\Services\MikroTik\MikroTikService;
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
        MikroTikService $mikrotik
    ): RedirectResponse {
        $data = $request->validated();

        $router = Router::create($data);

        $live = $mikrotik->inspect($router);

        $this->saveLiveStatus(
            $router,
            $live
        );

        $message = $live['success']
            ? 'Router added and MikroTik API connected successfully.'
            : 'Router added, but MikroTik API connection failed: '
                . ($live['message'] ?? 'Unknown error');

        return redirect()
            ->route('routers.index')
            ->with(
                $live['success'] ? 'success' : 'error',
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
        MikroTikService $mikrotik
    ): RedirectResponse {
        $data = $request->validated();

        /*
         * Blank password দিলে পুরোনো password থাকবে।
         */
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $router->update($data);
        $router->refresh();

        $live = $mikrotik->inspect($router);

        $this->saveLiveStatus(
            $router,
            $live
        );

        $message = $live['success']
            ? 'Router updated and MikroTik sync completed.'
            : 'Router updated, but connection failed: '
                . ($live['message'] ?? 'Unknown error');

        return redirect()
            ->route('routers.index')
            ->with(
                $live['success'] ? 'success' : 'error',
                $message
            );
    }

    public function sync(
        Router $router,
        MikroTikService $mikrotik
    ): RedirectResponse {
        $live = $mikrotik->inspect($router);

        $this->saveLiveStatus(
            $router,
            $live
        );

        if (!$live['success']) {
            return back()->with(
                'error',
                'MikroTik sync failed: '
                    . ($live['message'] ?? 'Unknown error')
            );
        }

        return back()->with(
            'success',
            'MikroTik sync completed. Identity: '
                . ($live['identity'] ?? $router->name)
                . ', RouterOS: '
                . ($live['version'] ?? 'Unknown')
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
