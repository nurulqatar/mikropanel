<?php

namespace App\Jobs;

use App\Models\HotspotServer;
use App\Models\Router;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverHotspotServersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;
    public int $backoff = 5;

    public function __construct()
    {
        $this->onQueue(
            'router-sync'
        );
    }

    public function handle(
        HotspotRouterService $service
    ): void {
        Router::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->each(
                function (
                    Router $router
                ) use ($service): void {
                    try {
                        $rows =
                            $service->discover(
                                $router
                            );

                        $foundNames = [];

                        foreach ($rows as $row) {
                            $name =
                                $row['name']
                                ?? null;

                            if (!$name) {
                                continue;
                            }

                            $foundNames[] =
                                $name;

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

                                        'dns_name' =>
                                            $row[
                                                '_dns_name'
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
                        }

                        $missing =
                            HotspotServer::query()
                                ->where(
                                    'router_id',
                                    $router->id
                                );

                        if ($foundNames !== []) {
                            $missing->whereNotIn(
                                'mikrotik_name',
                                $foundNames
                            );
                        }

                        $missing->update([
                            'connected' =>
                                false,
                        ]);

                    } catch (Throwable $e) {
                        HotspotServer::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->update([
                                'connected' =>
                                    false,

                                'last_error' =>
                                    $e
                                        ->getMessage(),
                            ]);

                        Log::error(
                            'Hotspot discovery failed.',
                            [
                                'router_id' =>
                                    $router->id,

                                'message' =>
                                    $e
                                        ->getMessage(),
                            ]
                        );

                        throw $e;
                    }
                }
            );
    }
}
