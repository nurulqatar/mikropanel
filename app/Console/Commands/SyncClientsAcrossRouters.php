<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientRouterBinding;
use App\Models\Router;
use App\Services\ClientProvisionService;
use Illuminate\Console\Command;

class SyncClientsAcrossRouters extends Command
{
    protected $signature =
        'clients:sync-routers
        {--client= : Only one client ID}
        {--router= : Only one router ID}
        {--force : Re-sync already synced bindings}
        {--dry-run : Show work without changing anything}';

    protected $description =
        'Synchronize eligible clients across enabled MikroTik routers';

    public function handle(
        ClientProvisionService $provision
    ): int {
        $clientId =
            $this->option(
                'client'
            );

        $routerId =
            $this->option(
                'router'
            );

        $force =
            (bool)
            $this->option(
                'force'
            );

        $dryRun =
            (bool)
            $this->option(
                'dry-run'
            );

        $routers =
            Router::query()
                ->where(
                    'enabled',
                    true
                )
                ->when(
                    $routerId,
                    fn ($query) =>
                        $query->where(
                            'id',
                            $routerId
                        )
                )
                ->orderBy('id')
                ->get();

        $counts = [
            'would_sync' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'removed' => 0,
        ];

        /*
         * TENANT_SAFE_ROUTER_SYNC_V2
         *
         * Each router receives only:
         *
         * - clients from the same reseller;
         * - same-zone clients;
         * - same-reseller all-zone package clients;
         * - clients already bound to that router,
         *   for convergence/cleanup.
         *
         * A platform router can never attempt
         * synchronization of reseller clients.
         */
        foreach ($routers as $router) {
            /*
             * First clean archived clients that
             * still have a non-removed binding
             * specifically to this router.
             */
            $archived =
                Client::withoutGlobalScopes()
                    ->whereNotNull(
                        'clients.deleted_at'
                    )
                    ->when(
                        $clientId,
                        fn ($query) =>
                            $query->where(
                                'clients.id',
                                $clientId
                            )
                    )
                    ->whereHas(
                        'routerBindings',
                        function (
                            $bindingQuery
                        ) use (
                            $router
                        ): void {
                            $bindingQuery
                                ->where(
                                    'router_id',
                                    $router->id
                                )
                                ->where(
                                    'sync_status',
                                    '!=',
                                    'removed'
                                );
                        }
                    )
                    ->orderBy(
                        'clients.id'
                    )
                    ->get();

            foreach (
                $archived
                as $client
            ) {
                if ($dryRun) {
                    $counts[
                        'would_sync'
                    ]++;

                    continue;
                }

                $ok =
                    $provision
                        ->syncClientToRouter(
                            $client,
                            $router
                        );

                if ($ok) {
                    $counts[
                        'removed'
                    ]++;
                } else {
                    $counts[
                        'failed'
                    ]++;
                }
            }

            $clients =
                Client::withoutGlobalScopes()
                    ->whereNull(
                        'clients.deleted_at'
                    )
                    ->when(
                        $clientId,
                        fn ($query) =>
                            $query->where(
                                'clients.id',
                                $clientId
                            )
                    )
                    ->where(
                        function (
                            $tenantQuery
                        ) use (
                            $router
                        ): void {
                            if (
                                $router
                                    ->reseller_id
                                === null
                            ) {
                                $tenantQuery
                                    ->whereNull(
                                        'clients.reseller_id'
                                    );

                                return;
                            }

                            $tenantQuery
                                ->where(
                                    'clients.reseller_id',
                                    $router
                                        ->reseller_id
                                );
                        }
                    )
                    ->where(
                        function (
                            $eligibilityQuery
                        ) use (
                            $router
                        ): void {
                            $eligibilityQuery
                                ->where(
                                    'clients.zone_id',
                                    $router->zone_id
                                )
                                ->orWhereHas(
                                    'package',
                                    function (
                                        $packageQuery
                                    ): void {
                                        $packageQuery
                                            ->withoutGlobalScopes()
                                            ->where(
                                                'coverage_mode',
                                                'all_zones'
                                            );
                                    }
                                )
                                ->orWhereHas(
                                    'routerBindings',
                                    function (
                                        $bindingQuery
                                    ) use (
                                        $router
                                    ): void {
                                        $bindingQuery
                                            ->where(
                                                'router_id',
                                                $router->id
                                            );
                                    }
                                );
                        }
                    )
                    ->orderBy(
                        'clients.id'
                    )
                    ->get();

            foreach ($clients as $client) {
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

                if (
                    !$force
                    && $binding
                    && $binding
                        ->sync_status
                        === 'synced'
                ) {
                    $counts[
                        'skipped'
                    ]++;

                    continue;
                }

                if ($dryRun) {
                    $counts[
                        'would_sync'
                    ]++;

                    continue;
                }

                $ok =
                    $provision
                        ->syncClientToRouter(
                            $client,
                            $router
                        );

                if ($ok) {
                    $counts[
                        'synced'
                    ]++;
                } else {
                    $counts[
                        'failed'
                    ]++;
                }
            }
        }

        $this->table(
            [
                'Result',
                'Count',
            ],
            [
                [
                    'Would Sync',
                    $counts[
                        'would_sync'
                    ],
                ],
                [
                    'Synced',
                    $counts[
                        'synced'
                    ],
                ],
                [
                    'Removed',
                    $counts[
                        'removed'
                    ],
                ],
                [
                    'Failed',
                    $counts[
                        'failed'
                    ],
                ],
                [
                    'Skipped',
                    $counts[
                        'skipped'
                    ],
                ],
            ]
        );

        return self::SUCCESS;
    }
}
