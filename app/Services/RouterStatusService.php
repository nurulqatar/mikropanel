<?php

namespace App\Services;

use App\Models\Router;
use App\Services\MikroTik\MikroTikService;

class RouterStatusService
{
    public function __construct(
        protected MikroTikService $mikrotik
    ) {
    }

    public function refresh(
        Router $router
    ): array {
        if (!$router->enabled) {
            $live = [
                'success' => false,
                'disabled' => true,
                'message' =>
                    'Router is disabled.',
                'latency_ms' => null,
                'sync_duration_ms' => null,
                'checked_at' =>
                    now()->toISOString(),
            ];
        } else {
            $live =
                $this->mikrotik
                    ->inspect($router);
        }

        $this->persist(
            $router,
            $live
        );

        return $live;
    }

    public function stored(
        Router $router
    ): array {
        return [
            'success' =>
                (bool) (
                    $router->enabled
                    && $router->connected
                ),

            'disabled' =>
                !$router->enabled,

            'message' =>
                !$router->enabled
                    ? 'Router is disabled.'
                    : (
                        $router->connected
                            ? 'Stored background RouterOS status.'
                            : (
                                $router->last_error
                                ?: 'Router has not completed a successful background sync.'
                            )
                    ),

            'latency_ms' =>
                $router->api_latency_ms,

            'sync_duration_ms' =>
                $router->sync_duration_ms,

            'checked_at' =>
                $router->last_checked_at
                    ?->toISOString(),

            'identity' =>
                $router->identity,

            'version' =>
                $router->routeros_version,

            'board_name' =>
                $router->board_name,

            'uptime' =>
                $router->uptime,

            'cpu_load' =>
                $router->cpu_load,

            'free_memory' =>
                $router->free_memory,

            'total_memory' =>
                $router->total_memory,

            'dhcp_leases_count' =>
                $router->dhcp_leases_count,

            'arp_entries_count' =>
                $router->arp_entries_count,

            'simple_queues_count' =>
                $router->simple_queues_count,
        ];
    }

    public function persist(
        Router $router,
        array $live
    ): void {
        $values = [
            'connected' =>
                (bool) (
                    $live['success']
                    ?? false
                ),

            'last_checked_at' =>
                now(),

            'last_error' =>
                ($live['success'] ?? false)
                    ? null
                    : (
                        $live['message']
                        ?? 'Unknown error'
                    ),

            'api_latency_ms' =>
                $live['latency_ms']
                ?? null,

            'sync_duration_ms' =>
                $live['sync_duration_ms']
                ?? null,
        ];

        if ($live['success'] ?? false) {
            $values['last_seen_at'] =
                now();

            $fieldMap = [
                'identity' =>
                    'identity',

                'routeros_version' =>
                    'version',

                'board_name' =>
                    'board_name',

                'uptime' =>
                    'uptime',

                'cpu_load' =>
                    'cpu_load',

                'free_memory' =>
                    'free_memory',

                'total_memory' =>
                    'total_memory',

                'dhcp_leases_count' =>
                    'dhcp_leases_count',

                'arp_entries_count' =>
                    'arp_entries_count',

                'simple_queues_count' =>
                    'simple_queues_count',
            ];

            foreach (
                $fieldMap
                as $column => $liveKey
            ) {
                if (
                    array_key_exists(
                        $liveKey,
                        $live
                    )
                    && $live[$liveKey]
                        !== null
                ) {
                    $values[$column] =
                        $live[$liveKey];
                }
            }
        }

        $router->forceFill(
            $values
        );

        $router->saveQuietly();
    }
}
