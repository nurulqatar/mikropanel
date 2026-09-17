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
        /*
         * HOTSPOT_ROUTER_ZONE_FILTER_V2
         *
         * New routers:
         *   only Hotspot-zone MikroTik routers are
         *   eligible for Hotspot discovery.
         *
         * Legacy compatibility:
         *   a Router that already owns a HotspotServer
         *   remains eligible even if its historical
         *   Router.zone_id is a MAC zone.
         *
         * Therefore adding a normal new MAC router can
         * never accidentally activate Hotspot discovery.
         */
        Router::query()
            ->with([
                'zone:id,reseller_id,name,code,service_type,enabled',
            ])
            ->where(
                'enabled',
                true
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereHas(
                            'zone',
                            function ($zone): void {
                                $zone
                                    ->where(
                                        'service_type',
                                        'hotspot'
                                    )
                                    ->where(
                                        'enabled',
                                        true
                                    );
                            }
                        )
                        ->orWhereIn(
                            'id',
                            HotspotServer::query()
                                ->select(
                                    'router_id'
                                )
                        );
                }
            )
            ->orderBy('id')
            ->each(
                function (
                    Router $router
                ) use ($service): void {
                    /*
                     * New Hotspot router:
                     * inherit its own Hotspot zone.
                     *
                     * Legacy mixed router:
                     * preserve the existing Hotspot
                     * server's established Hotspot zone.
                     */
                    $legacyServer =
                        HotspotServer::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->orderBy('id')
                            ->first([
                                'zone_id',
                                'reseller_id',
                            ]);

                    $routerIsHotspot =
                        $router->zone
                        && $router
                            ->zone
                            ->service_type
                            === 'hotspot';

                    $targetZoneId =
                        $routerIsHotspot
                            ? (int)
                                $router->zone_id
                            : (
                                $legacyServer
                                    ? (int)
                                        $legacyServer
                                            ->zone_id
                                    : null
                            );

                    $targetResellerId =
                        $routerIsHotspot
                            ? (
                                $router->reseller_id
                                    ? (int)
                                        $router
                                            ->reseller_id
                                    : null
                            )
                            : (
                                $legacyServer
                                    ? (
                                        $legacyServer
                                            ->reseller_id
                                            ? (int)
                                                $legacyServer
                                                    ->reseller_id
                                            : null
                                    )
                                    : null
                            );

                    if (!$targetZoneId) {
                        return;
                    }

                    try {
                        $rows =
                            $service->discover(
                                $router
                            );

                        $foundNames = [];

                        foreach (
                            $rows
                            as $row
                        ) {
                            $name =
                                $row['name']
                                ?? null;

                            if (!$name) {
                                continue;
                            }

                            $foundNames[] =
                                $name;

                            /*
                             * HOTSPOT_PARENT_ZONE_INHERIT_V2
                             */
                            HotspotServer::query()
                                ->updateOrCreate(
                                    [
                                        'router_id' =>
                                            $router->id,

                                        'mikrotik_name' =>
                                            $name,
                                    ],
                                    [
                                        'reseller_id' =>
                                            $targetResellerId,

                                        'zone_id' =>
                                            $targetZoneId,

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
                                                ]
                                                ?? 'no'
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
                                )
                                ->where(
                                    'zone_id',
                                    $targetZoneId
                                );

                        if (
                            $foundNames !== []
                        ) {
                            $missing
                                ->whereNotIn(
                                    'mikrotik_name',
                                    $foundNames
                                );
                        }

                        $missing->update([
                            'connected' =>
                                false,
                        ]);

                    } catch (
                        Throwable $exception
                    ) {
                        /*
                         * One unreachable remote site
                         * must not prevent discovery on
                         * the other Hotspot zones.
                         */
                        HotspotServer::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->where(
                                'zone_id',
                                $targetZoneId
                            )
                            ->update([
                                'connected' =>
                                    false,

                                'last_error' =>
                                    $exception
                                        ->getMessage(),
                            ]);

                        Log::warning(
                            'Hotspot discovery skipped an unavailable router.',
                            [
                                'router_id' =>
                                    $router->id,

                                'zone_id' =>
                                    $targetZoneId,

                                'message' =>
                                    $exception
                                        ->getMessage(),
                            ]
                        );
                    }
                }
            );
    }
}
