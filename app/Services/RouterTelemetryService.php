<?php

namespace App\Services;

use App\Models\Router;
use App\Models\Setting;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Support\Facades\Log;

class RouterTelemetryService
{
    public function __construct(
        protected MikroTikService $mikrotik,
        protected RouterStatusService $status,
        protected ClientConnectionService $connections,
        protected QueueUsageService $queueUsage,
        protected ClientUsageService $usage
    ) {
    }

    public function sync(
        Router $router
    ): array {
        $telemetry =
            $this->mikrotik
                ->telemetry(
                    $router
                );

        $this->status->persist(
            $router,
            $telemetry
        );

        $result = [
            'success' =>
                (bool) (
                    $telemetry[
                        'success'
                    ]
                    ?? false
                ),

            'api_latency_ms' =>
                $telemetry[
                    'latency_ms'
                ]
                ?? null,

            'sync_duration_ms' =>
                $telemetry[
                    'sync_duration_ms'
                ]
                ?? null,

            'query_count' =>
                $telemetry[
                    'routeros_query_count'
                ]
                ?? null,

            'connection_reused' =>
                (bool) (
                    $telemetry[
                        'connection_reused'
                    ]
                    ?? false
                ),

            'connection_stats' =>
                null,

            'usage_stats' =>
                null,
        ];

        if (
            !($telemetry['success']
                ?? false)
        ) {
            Log::warning(
                'Router telemetry sync failed.',
                [
                    'router_id' =>
                        $router->id,

                    'message' =>
                        $telemetry[
                            'message'
                        ]
                        ?? 'Unknown error',
                ]
            );

            return $result;
        }

        if (
            Setting::bool(
                'connection_sync_enabled',
                true
            )
        ) {
            if (
                $telemetry['leases_ok']
                ?? false
            ) {
                $result[
                    'connection_stats'
                ] =
                    $this->connections
                        ->syncRouterFromLeases(
                            $router,
                            $telemetry[
                                'leases'
                            ]
                            ?? []
                        );
            } else {
                Log::warning(
                    'DHCP telemetry read failed; client connection states were preserved.',
                    [
                        'router_id' =>
                            $router->id,

                        'message' =>
                            $telemetry[
                                'leases_error'
                            ]
                            ?? 'Unknown DHCP read error',
                    ]
                );
            }
        }

        if (
            Setting::bool(
                'usage_sync_enabled',
                true
            )
        ) {
            if (
                $telemetry['queues_ok']
                ?? false
            ) {
                $queueMap =
                    $this->queueUsage
                        ->normalizeRows(
                            $telemetry[
                                'queues'
                            ]
                            ?? []
                        );

                $result[
                    'usage_stats'
                ] =
                    $this->usage
                        ->syncRouterFromQueues(
                            $router,
                            $queueMap
                        );
            } else {
                Log::warning(
                    'Queue telemetry read failed; usage counters were preserved.',
                    [
                        'router_id' =>
                            $router->id,

                        'message' =>
                            $telemetry[
                                'queues_error'
                            ]
                            ?? 'Unknown Queue read error',
                    ]
                );
            }
        }

        Log::info(
            'Router telemetry synchronized.',
            [
                'router_id' =>
                    $router->id,

                'api_latency_ms' =>
                    $result[
                        'api_latency_ms'
                    ],

                'sync_duration_ms' =>
                    $result[
                        'sync_duration_ms'
                    ],

                'routeros_queries' =>
                    $result[
                        'query_count'
                    ],

                'connection_reused' =>
                    $result[
                        'connection_reused'
                    ],

                'connection_stats' =>
                    $result[
                        'connection_stats'
                    ],

                'usage_stats' =>
                    $result[
                        'usage_stats'
                    ],

                'leases_ok' =>
                    $telemetry[
                        'leases_ok'
                    ]
                    ?? false,

                'queues_ok' =>
                    $telemetry[
                        'queues_ok'
                    ]
                    ?? false,

                'arp_ok' =>
                    $telemetry[
                        'arp_ok'
                    ]
                    ?? false,
            ]
        );

        return $result;
    }
}
