<?php

namespace App\Console\Commands;

use App\Services\ClientConnectionService;
use Illuminate\Console\Command;

class SyncClientConnections extends Command
{
    protected $signature =
        'clients:sync-connection';

    protected $description =
        'Sync client online and offline status from MikroTik DHCP leases';

    public function handle(
        ClientConnectionService $service
    ): int {
        $stats = $service->syncAll();

        $this->info(
            'Connection sync completed. '
            . "Online: {$stats['online']}, "
            . "Offline: {$stats['offline']}, "
            . "Updated: {$stats['updated']}, "
            . "Failed: {$stats['failed']}"
        );

        return self::SUCCESS;
    }
}
