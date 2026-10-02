<?php

namespace App\Console\Commands;

use App\Services\Rental\RentalMasterService;
use Illuminate\Console\Command;

class RentalMasterMaintain extends Command
{
    protected $signature = 'rentals:master-maintain {--sync-only : Only synchronize source rentals}';
    protected $description = 'Synchronize and maintain Company, Hotel and Compliance commercial rentals.';

    public function handle(RentalMasterService $service): int
    {
        $result = $this->option('sync-only')
            ? ['synced' => $service->syncAll()]
            : $service->maintain();

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
