<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionHotspotVoucher;
use App\Jobs\SyncHotspotServer;
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

        HotspotServer::query()
            ->where('enabled', true)
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
         * Convergence retry:
         * a sold voucher that failed its first
         * RouterOS provisioning attempt will
         * be queued again automatically.
         */
        HotspotVoucher::query()
            ->whereNotNull('sold_at')
            ->whereNull(
                'mikrotik_user_id'
            )
            ->whereIn(
                'status',
                [
                    'unused',
                    'active',
                ]
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

        $this->info(
            "HOTSPOT_SERVERS_QUEUED={$servers}"
        );

        $this->info(
            "HOTSPOT_VOUCHERS_QUEUED={$vouchers}"
        );

        return self::SUCCESS;
    }
}
