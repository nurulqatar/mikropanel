<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionHotspotVoucher;
use App\Jobs\SyncHotspotServer;
use App\Jobs\SuspendHotspotVoucher;
use App\Models\HotspotServer;
use App\Models\HotspotVoucher;
use Illuminate\Console\Command;

class SyncHotspotServers extends Command
{
    protected $signature =
        'hotspot:sync';

    protected $description =
        'Queue Hotspot synchronization and missing voucher provisioning';

    public function handle(): int
    {
        $servers = 0;
        $vouchers = 0;
        $suspends = 0;

        HotspotServer::query()
            ->where('enabled', true)
            /*
             * HOTSPOT_OFFLINE_COOLDOWN_V1
             *
             * Connected routers stay near-real-time.
             * Offline routers retry every five minutes
             * instead of creating a timeout every minute.
             */
            ->where(
                function ($query): void {
                    $query
                        ->where(
                            'connected',
                            true
                        )
                        ->orWhereNull(
                            'last_synced_at'
                        )
                        ->orWhere(
                            'last_synced_at',
                            '<=',
                            now()
                                ->subMinutes(5)
                        );
                }
            )
            ->orderBy('id')
            ->each(
                function (
                    HotspotServer $server
                ) use (&$servers): void {
                    SyncHotspotServer::dispatch(
                        $server->id
                    );

                    $servers++;
                }
            );

        /*
         * ZONE_VOUCHER_CONVERGENCE_V10
         *
         * An offline router must never cause hundreds of per-voucher
         * socket timeouts every minute. Only routers with a currently
         * connected enabled Hotspot server participate in convergence.
         * SyncHotspotServer keeps probing offline servers on its cooldown;
         * as soon as connected=true, the next scheduler pass converges.
         */
        $connectedTarget =
            function ($router): void {
                $router
                    ->selectRaw('1')
                    ->from('routers')
                    ->whereColumn(
                        'routers.zone_id',
                        'hotspot_vouchers.zone_id'
                    )
                    ->where(
                        'routers.enabled',
                        true
                    )
                    ->where(
                        function ($tenant): void {
                            $tenant
                                ->whereNull(
                                    'hotspot_vouchers.reseller_id'
                                )
                                ->orWhereColumn(
                                    'routers.reseller_id',
                                    'hotspot_vouchers.reseller_id'
                                );
                        }
                    )
                    ->whereExists(
                        function ($server): void {
                            $server
                                ->selectRaw('1')
                                ->from('hotspot_servers')
                                ->whereColumn(
                                    'hotspot_servers.router_id',
                                    'routers.id'
                                )
                                ->whereColumn(
                                    'hotspot_servers.zone_id',
                                    'hotspot_vouchers.zone_id'
                                )
                                ->where(
                                    'hotspot_servers.enabled',
                                    true
                                )
                                ->where(
                                    'hotspot_servers.connected',
                                    true
                                )
                                ->where(
                                    function ($tenant): void {
                                        $tenant
                                            ->whereNull(
                                                'hotspot_vouchers.reseller_id'
                                            )
                                            ->orWhereColumn(
                                                'hotspot_servers.reseller_id',
                                                'hotspot_vouchers.reseller_id'
                                            );
                                    }
                                );
                        }
                    );
            };

        HotspotVoucher::query()
            ->whereNotNull('zone_id')
            ->whereIn(
                'status',
                [
                    'unused',
                    'active',
                ]
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereNull('expires_at')
                        ->orWhere(
                            'expires_at',
                            '>',
                            now()
                        );
                }
            )
            ->whereExists(
                function ($router) use (
                    $connectedTarget
                ): void {
                    $connectedTarget($router);

                    $router->whereNotExists(
                        function ($sync): void {
                            $sync
                                ->selectRaw('1')
                                ->from(
                                    'hotspot_voucher_router_syncs'
                                )
                                ->whereColumn(
                                    'hotspot_voucher_router_syncs.hotspot_voucher_id',
                                    'hotspot_vouchers.id'
                                )
                                ->whereColumn(
                                    'hotspot_voucher_router_syncs.router_id',
                                    'routers.id'
                                )
                                ->where(
                                    'hotspot_voucher_router_syncs.status',
                                    'synced'
                                );
                        }
                    );
                }
            )
            ->orderBy('id')
            ->limit(500)
            ->each(
                function (
                    HotspotVoucher $voucher
                ) use (&$vouchers): void {
                    ProvisionHotspotVoucher::dispatch(
                        $voucher->id
                    );

                    $vouchers++;
                }
            );

        /*
         * Reconnect lifecycle convergence:
         * if a router was offline when a voucher became suspended,
         * expired or archived, disable it as soon as that router's
         * Hotspot server is connected again.
         */
        HotspotVoucher::withTrashed()
            ->whereNotNull('zone_id')
            ->whereIn(
                'status',
                [
                    'suspended',
                    'expired',
                    'archived',
                ]
            )
            ->whereExists(
                function ($router) use (
                    $connectedTarget
                ): void {
                    $connectedTarget($router);

                    $router->whereNotExists(
                        function ($sync): void {
                            $sync
                                ->selectRaw('1')
                                ->from(
                                    'hotspot_voucher_router_syncs'
                                )
                                ->whereColumn(
                                    'hotspot_voucher_router_syncs.hotspot_voucher_id',
                                    'hotspot_vouchers.id'
                                )
                                ->whereColumn(
                                    'hotspot_voucher_router_syncs.router_id',
                                    'routers.id'
                                )
                                ->where(
                                    'hotspot_voucher_router_syncs.status',
                                    'suspended'
                                );
                        }
                    );
                }
            )
            ->orderBy('id')
            ->limit(500)
            ->each(
                function (
                    HotspotVoucher $voucher
                ) use (&$suspends): void {
                    SuspendHotspotVoucher::dispatch(
                        $voucher->id
                    );

                    $suspends++;
                }
            );

        $this->info(
            "HOTSPOT_SERVERS_QUEUED={$servers}"
        );

        $this->info(
            "HOTSPOT_VOUCHERS_QUEUED={$vouchers}"
        );

        $this->info(
            "HOTSPOT_SUSPENDS_QUEUED={$suspends}"
        );

        return self::SUCCESS;
    }
}
