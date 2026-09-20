<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ComplianceRentalMaintain extends Command
{
    protected $signature =
        'compliance:rental-maintain';

    protected $description =
        'Expire Compliance subscriptions without removing router filter policy.';

    public function handle(): int
    {
        $rentals = DB::table('compliance_rentals')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        $subscriptions = DB::table(
            'compliance_subscriptions'
        )
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        $this->line(
            "RENTALS={$rentals} SUBSCRIPTIONS={$subscriptions}"
        );

        /*
         * No RouterOS call here.
         * Filtering last-known policy remains.
         */

        return self::SUCCESS;
    }
}
