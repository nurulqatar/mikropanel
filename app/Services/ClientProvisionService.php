<?php

namespace App\Services;

use App\Exceptions\ClientProvisioningException;
use App\Models\Client;
use App\Models\ClientRouterBinding;
use App\Models\Router;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientProvisionService
{
    public function __construct(
        protected DhcpLeaseService $dhcpLeaseService,
        protected ArpService $arpService,
        protected QueueService $queueService,
    ) {
    }

    /*
     * New client:
     * save one global client/IP in panel,
     * then converge that state across every
     * enabled MikroTik independently.
     */
    public function provision(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $this->syncAcrossEnabledRouters(
            $client
        );

        $this->syncLegacyPrimaryIds(
            $client
        );
    }

    /*
     * Existing client update.
     *
     * Router failures are NOT allowed to stop
     * the panel update. Failed routers remain
     * marked for automatic retry.
     */
    public function update(
        Client $client,
        ?Client $rollbackClient = null
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $this->syncAcrossEnabledRouters(
            $client
        );
    }

    public function suspend(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $desired = clone $client;

        $desired->setRelations(
            $client->getRelations()
        );

        $desired->forceFill([
            'enabled' => false,
            'connected' => false,
        ]);

        $this->syncAcrossEnabledRouters(
            $desired
        );

        try {
            $client->forceFill([
                'enabled' => false,
                'connected' => false,
            ])->save();

            $this->syncLegacyPrimaryIds(
                $client
            );

        } catch (Throwable $exception) {
            throw new ClientProvisioningException(
                'Unable to save suspended client state.',
                false,
                $exception
            );
        }
    }

    public function unsuspend(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $desired = clone $client;

        $desired->setRelations(
            $client->getRelations()
        );

        $desired->forceFill([
            'enabled' => true,
        ]);

        $this->syncAcrossEnabledRouters(
            $desired
        );

        try {
            $client->forceFill([
                'enabled' => true,
            ])->save();

            $this->syncLegacyPrimaryIds(
                $client
            );

        } catch (Throwable $exception) {
            throw new ClientProvisioningException(
                'Unable to save active client state.',
                false,
                $exception
            );
        }
    }

    /*
     * Archive cleanup.
     *
     * Reachable routers are cleaned now.
     * Offline routers remain failed and the
     * background command retries later.
     */
    public function remove(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $bindings = ClientRouterBinding::query()
            ->where(
                'client_id',
                $client->id
            )
            ->with('router')
            ->get();

        foreach ($bindings as $binding) {
            $router = $binding->router;

            if (!$router) {
                continue;
            }

            $this->removeFromRouter(
                $client,
                $router,
                $binding
            );
        }

        $client->forceFill([
            'mikrotik_lease_id' => null,
            'mikrotik_arp_id' => null,
            'mikrotik_queue_id' => null,
        ])->save();
    }

    /*
     * Public entry used by automatic retry
     * command and future RouterController hook.
     */
    public function syncClientToRouter(
        Client $client,
        Router $router
    ): bool {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        if ($client->trashed()) {
            $binding =
                ClientRouterBinding::query()
                    ->where(
                        'client_id',
                        $client->id
                    )
                    ->where(
                        'router_id',
                        $router->id
                    )
                    ->first();

            if (!$binding) {
                return true;
            }

            return $this->removeFromRouter(
                $client,
                $router,
                $binding
            );
        }

        $ok = $this->syncOneRouter(
            $client,
            $router
        );

        if (
            $ok
            && (int) $client->router_id
                === (int) $router->id
        ) {
            $this->syncLegacyPrimaryIds(
                $client
            );
        }

        return $ok;
    }

    private function syncAcrossEnabledRouters(
        Client $client
    ): array {
        $routers = Router::query()
            ->where(
                'enabled',
                true
            )
            ->orderBy('id')
            ->get();

        $result = [
            'synced' => 0,
            'failed' => 0,
        ];

        foreach ($routers as $router) {
            if (
                $this->syncOneRouter(
                    $client,
                    $router
                )
            ) {
                $result['synced']++;
            } else {
                $result['failed']++;
            }
        }

        if ($routers->isEmpty()) {
            Log::warning(
                'Global client sync found no enabled routers.',
                [
                    'client_id' =>
                        $client->id,
                ]
            );
        }

        return $result;
    }

    /*
     * Converge one router to the desired
     * panel state.
     *
     * Same client_code, MAC and GLOBAL IP
     * are used on every MikroTik.
     */
    private function syncOneRouter(
        Client $client,
        Router $router
    ): bool {
        $binding =
            ClientRouterBinding::firstOrCreate(
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,
                ],
                [
                    'sync_status' =>
                        'pending',
                ]
            );

        $binding->forceFill([
            'sync_status' => 'pending',
            'last_error' => null,
        ])->save();

        try {
            $context = $this->context(
                $client,
                $router,
                $binding
            );

            /*
             * CREATE calls are idempotent:
             * existing objects are found by
             * client_code / queue name.
             */
            $leaseId =
                $this->dhcpLeaseService
                    ->create($context);

            $binding->forceFill([
                'mikrotik_lease_id' =>
                    $leaseId,
            ])->save();

            $context->forceFill([
                'mikrotik_lease_id' =>
                    $leaseId,
            ]);

            $arpId =
                $this->arpService
                    ->create($context);

            $binding->forceFill([
                'mikrotik_arp_id' =>
                    $arpId,
            ])->save();

            $context->forceFill([
                'mikrotik_arp_id' =>
                    $arpId,
            ]);

            $queueId =
                $this->queueService
                    ->create($context);

            $binding->forceFill([
                'mikrotik_queue_id' =>
                    $queueId,
            ])->save();

            $context->forceFill([
                'mikrotik_queue_id' =>
                    $queueId,
            ]);

            /*
             * Force current MAC/IP/package
             * values even when objects already
             * existed before this sync.
             */
            $this->dhcpLeaseService
                ->update($context);

            $this->arpService
                ->update($context);

            $this->queueService
                ->update($context);

            /*
             * Desired enabled state is also
             * converged on every router.
             */
            if ($client->enabled) {
                $this->dhcpLeaseService
                    ->enable($context);

                $this->arpService
                    ->enable($context);

                $this->queueService
                    ->enable($context);

            } else {
                $this->queueService
                    ->disable($context);

                $this->arpService
                    ->disable($context);

                $this->dhcpLeaseService
                    ->disable($context);
            }

            $binding->forceFill([
                'sync_status' =>
                    'synced',

                'last_synced_at' =>
                    now(),

                'last_error' =>
                    null,
            ])->save();

            return true;

        } catch (Throwable $exception) {
            $binding->forceFill([
                'sync_status' =>
                    'failed',

                'last_error' =>
                    $exception
                        ->getMessage(),
            ])->save();

            Log::warning(
                'Client router sync failed.',
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,

                    'router_name' =>
                        $router->name,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return false;
        }
    }

    private function removeFromRouter(
        Client $client,
        Router $router,
        ClientRouterBinding $binding
    ): bool {
        $context = $this->context(
            $client,
            $router,
            $binding
        );

        $errors = [];

        foreach (
            [
                $this->queueService,
                $this->arpService,
                $this->dhcpLeaseService,
            ]
            as $service
        ) {
            try {
                $service->remove(
                    $context
                );

            } catch (Throwable $exception) {
                $errors[] =
                    $service::class
                    . ': '
                    . $exception
                        ->getMessage();
            }
        }

        if ($errors !== []) {
            $binding->forceFill([
                'sync_status' =>
                    'failed',

                'last_error' =>
                    implode(
                        ' | ',
                        $errors
                    ),
            ])->save();

            Log::warning(
                'Archived client router cleanup failed.',
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,

                    'errors' =>
                        $errors,
                ]
            );

            return false;
        }

        $binding->forceFill([
            'mikrotik_lease_id' =>
                null,

            'mikrotik_arp_id' =>
                null,

            'mikrotik_queue_id' =>
                null,

            'sync_status' =>
                'removed',

            'last_synced_at' =>
                now(),

            'last_error' =>
                null,
        ])->save();

        return true;
    }

    private function context(
        Client $source,
        Router $router,
        ClientRouterBinding $binding
    ): Client {
        $context = clone $source;

        $context->setRelations(
            $source->getRelations()
        );

        $context->forceFill([
            /*
             * Runtime router only.
             * This clone is NEVER saved.
             */
            'router_id' =>
                $router->id,

            'mikrotik_lease_id' =>
                $binding
                    ->mikrotik_lease_id,

            'mikrotik_arp_id' =>
                $binding
                    ->mikrotik_arp_id,

            'mikrotik_queue_id' =>
                $binding
                    ->mikrotik_queue_id,
        ]);

        $context->setRelation(
            'router',
            $router
        );

        return $context;
    }

    /*
     * Keep the old single-router columns
     * populated for existing monitoring code.
     *
     * They represent the client's historical
     * primary router only. Multi-router truth
     * lives in client_router_bindings.
     */
    private function syncLegacyPrimaryIds(
        Client $client
    ): void {
        if (!$client->router_id) {
            return;
        }

        $binding =
            ClientRouterBinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'router_id',
                    $client->router_id
                )
                ->first();

        if (!$binding) {
            return;
        }

        $client->forceFill([
            'mikrotik_lease_id' =>
                $binding
                    ->mikrotik_lease_id,

            'mikrotik_arp_id' =>
                $binding
                    ->mikrotik_arp_id,

            'mikrotik_queue_id' =>
                $binding
                    ->mikrotik_queue_id,
        ])->saveQuietly();
    }
}
