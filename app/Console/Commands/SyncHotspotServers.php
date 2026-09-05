<?php

namespace App\Console\Commands;

use App\Jobs\SyncHotspotServer;
use App\Models\HotspotServer;
use Illuminate\Console\Command;

class SyncHotspotServers extends Command
{
    protected $signature =
        'hotspot:sync';

    protected $description =
        'Queue Hotspot server/session synchronization';

    public function handle(): int
    {
        $count = 0;

        HotspotServer::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->each(
                function (
                    HotspotServer $server
                ) use (&$count): void {
                    SyncHotspotServer::dispatch(
                        $server->id
                    );

                    $count++;
                }
            );

        $this->info(
            "HOTSPOT_SYNC_QUEUED={$count}"
        );

        return self::SUCCESS;
    }
}
