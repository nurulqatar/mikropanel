<?php

namespace App\Console\Commands;

use App\Services\Reseller\ResellerSnapshotService;
use Illuminate\Console\Command;

class SnapshotResellerUsage extends Command
{
    protected $signature =
        'resellers:snapshot-usage';

    protected $description =
        'Store daily reseller usage snapshots';

    public function handle(
        ResellerSnapshotService $snapshots
    ): int {
        $count =
            $snapshots
                ->captureAll();

        $this->info(
            'SNAPSHOTS='
            . $count
        );

        return self::SUCCESS;
    }
}
