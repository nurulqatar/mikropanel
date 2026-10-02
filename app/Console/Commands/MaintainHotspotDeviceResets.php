<?php

namespace App\Console\Commands;

use App\Services\Hotspot\HotspotSelfDeviceResetService;
use Illuminate\Console\Command;

class MaintainHotspotDeviceResets extends Command
{
    protected $signature =
        'hotspot:device-reset-maintain';

    protected $description =
        'Capture the new MAC after a self-service Hotspot device reset';

    public function handle(
        HotspotSelfDeviceResetService $service
    ): int {
        $captured =
            $service
                ->captureRebinds();

        $this->info(
            "NEW_MAC_CAPTURED={$captured}"
        );

        return self::SUCCESS;
    }
}
