<?php

namespace App\Console\Commands;

use App\Models\HotspotServer;
use App\Models\Router;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Console\Command;
use Throwable;

class DiscoverHotspotServers extends Command
{
    protected $signature =
        'hotspot:discover';

    protected $description =
        'Discover MikroTik Hotspot servers';

    public function handle(
        HotspotRouterService $service
    ): int {
        $found = 0;

        Router::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->each(
                function (
                    Router $router
                ) use (
                    $service,
                    &$found
                ): void {
                    try {
                        $rows =
                            $service->discover(
                                $router
                            );

                        foreach ($rows as $row) {
                            $name =
                                $row['name']
                                ?? null;

                            if (!$name) {
                                continue;
                            }

                            HotspotServer::query()
                                ->updateOrCreate(
                                    [
                                        'router_id' =>
                                            $router->id,

                                        'mikrotik_name' =>
                                            $name,
                                    ],
                                    [
                                        'name' =>
                                            $name,

                                        'interface' =>
                                            $row[
                                                'interface'
                                            ] ?? null,

                                        'address_pool' =>
                                            $row[
                                                'address-pool'
                                            ] ?? null,

                                        'hotspot_profile' =>
                                            $row[
                                                'profile'
                                            ] ?? null,

                                        'enabled' =>
                                            (
                                                $row[
                                                    'disabled'
                                                ] ?? 'no'
                                            ) !== 'yes',

                                        'connected' =>
                                            true,

                                        'last_synced_at' =>
                                            now(),

                                        'last_error' =>
                                            null,
                                    ]
                                );

                            $found++;
                        }

                    } catch (Throwable $e) {
                        $this->warn(
                            'Router '
                            . $router->id
                            . ': '
                            . $e->getMessage()
                        );
                    }
                }
            );

        $this->info(
            "HOTSPOT_SERVERS_FOUND={$found}"
        );

        return self::SUCCESS;
    }
}
