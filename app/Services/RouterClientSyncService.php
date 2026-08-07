<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientRouterBinding;
use App\Models\Router;
use Throwable;

class RouterClientSyncService
{
    public function __construct(
        protected ClientProvisionService $provision
    ) {
    }

    /*
     * Synchronize EVERY non-archived client.
     *
     * Active clients become enabled.
     * Suspended/expired clients are created
     * but remain disabled.
     */
    public function syncAll(
        Router $router
    ): array {
        $result = [
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
        ];

        Client::query()
            ->orderBy('id')
            ->chunkById(
                50,
                function ($clients) use (
                    $router,
                    &$result
                ) {
                    foreach ($clients as $client) {
                        $result['total']++;

                        try {
                            $ok = $this
                                ->provision
                                ->syncClientToRouter(
                                    $client,
                                    $router
                                );

                            if ($ok) {
                                $result['synced']++;
                            } else {
                                $result['failed']++;
                            }

                        } catch (Throwable $exception) {
                            $result['failed']++;

                            ClientRouterBinding::updateOrCreate(
                                [
                                    'client_id' =>
                                        $client->id,

                                    'router_id' =>
                                        $router->id,
                                ],
                                [
                                    'sync_status' =>
                                        'failed',

                                    'last_error' =>
                                        $exception
                                            ->getMessage(),
                                ]
                            );
                        }
                    }
                }
            );

        return $result;
    }

    /*
     * Router itself is unreachable.
     * Avoid repeating one failed network
     * connection for every client.
     *
     * Bindings remain retryable by the
     * clients:sync-routers scheduler.
     */
    public function markRouterFailed(
        Router $router,
        string $message
    ): array {
        $result = [
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
        ];

        Client::query()
            ->orderBy('id')
            ->chunkById(
                100,
                function ($clients) use (
                    $router,
                    $message,
                    &$result
                ) {
                    foreach ($clients as $client) {
                        $result['total']++;
                        $result['failed']++;

                        ClientRouterBinding::updateOrCreate(
                            [
                                'client_id' =>
                                    $client->id,

                                'router_id' =>
                                    $router->id,
                            ],
                            [
                                'sync_status' =>
                                    'failed',

                                'last_error' =>
                                    $message,
                            ]
                        );
                    }
                }
            );

        return $result;
    }

    public function emptyResult(): array
    {
        return [
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
        ];
    }
}
