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
        'Synchronize global clients across every enabled MikroTik router';

    public function handle(
        ClientProvisionService $provision
    ): int {
        $clientId = $this->option(
            'client'
        );

        $routerId = $this->option(
            'router'
        );

        $force = (bool) $this->option(
            'force'
        );

        $dryRun = (bool) $this->option(
            'dry-run'
        );

        $routers = Router::query()
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

        $clients = Client::withTrashed()
            ->when(
                $clientId,
                fn ($query) =>
                    $query->where(
                        'id',
                        $clientId
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

        foreach ($clients as $client) {
            /*
             * Archived clients only need
             * cleanup of bindings that still
             * exist on routers.
             */
            if ($client->trashed()) {
                $bindings =
                    ClientRouterBinding::query()
                        ->where(
                            'client_id',
                            $client->id
                        )
                        ->where(
                            'sync_status',
                            '!=',
                            'removed'
                        )
                        ->when(
                            $routerId,
                            fn ($query) =>
                                $query->where(
                                    'router_id',
                                    $routerId
                                )
                        )
                        ->get();

                foreach (
                    $bindings
                    as $binding
                ) {
                    $router = Router::query()
                        ->where(
                            'id',
                            $binding->router_id
                        )
                        ->where(
                            'enabled',
                            true
                        )
                        ->first();

                    if (!$router) {
                        $counts['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $counts['would_sync']++;
                        continue;
                    }

                    $ok =
                        $provision
                            ->syncClientToRouter(
                                $client,
                                $router
                            );

                    if ($ok) {
                        $counts['removed']++;
                    } else {
                        $counts['failed']++;
                    }
                }

                continue;
            }

            foreach ($routers as $router) {
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
                    && $binding->sync_status
                        === 'synced'
                ) {
                    $counts['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $counts['would_sync']++;
                    continue;
                }

                $ok =
                    $provision
                        ->syncClientToRouter(
                            $client,
                            $router
                        );

                if ($ok) {
                    $counts['synced']++;
                } else {
                    $counts['failed']++;
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
                    $counts['would_sync'],
                ],
                [
                    'Synced',
                    $counts['synced'],
                ],
                [
                    'Removed',
                    $counts['removed'],
                ],
                [
                    'Failed',
                    $counts['failed'],
                ],
                [
                    'Skipped',
                    $counts['skipped'],
                ],
            ]
        );

        return self::SUCCESS;
    }
}
