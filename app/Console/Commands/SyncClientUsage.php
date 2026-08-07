<?php

namespace App\Console\Commands;

use App\Services\ClientUsageService;
use Illuminate\Console\Command;

class SyncClientUsage extends Command
{
    protected $signature = 'clients:sync-usage';

    protected $description =
        'Sync monthly client internet usage from MikroTik queues';

    public function handle(
        ClientUsageService $usageService
    ): int {
        $stats = $usageService->syncAll();

        $this->info(
            'Usage sync completed. '
            . "Synced: {$stats['synced']}, "
            . "Baselined: {$stats['baselined']}, "
            . "Missing: {$stats['missing']}, "
            . "Failed: {$stats['failed']}"
        );

        return self::SUCCESS;
    }
}
