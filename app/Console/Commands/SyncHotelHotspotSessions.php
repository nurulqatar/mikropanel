<?php

namespace App\Console\Commands;

use App\Services\Hotel\HotelHotspotTelemetryService;
use Illuminate\Console\Command;

class SyncHotelHotspotSessions extends Command
{
    protected $signature =
        'hotel-hotspot:sync-sessions';

    protected $description =
        'Synchronize Hotel Hotspot sessions and usage from MikroTik.';

    public function handle(
        HotelHotspotTelemetryService $service
    ): int {
        $result =
            $service->syncAll();

        $this->line(
            'ROUTERS='
            . $result['routers']
        );

        $this->line(
            'ONLINE='
            . $result['online']
        );

        $this->line(
            'SESSIONS_SEEN='
            . $result['sessions_seen']
        );

        $this->line(
            'FAILURES='
            . $result['failures']
        );

        foreach (
            $result['errors']
            as $error
        ) {
            $this->warn(
                'ROUTER '
                . $error['router_id']
                . ': '
                . $error['error']
            );
        }

        return self::SUCCESS;
    }
}
