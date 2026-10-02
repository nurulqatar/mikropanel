<?php

namespace App\Services\Reseller;

use App\Models\Reseller;
use App\Models\ResellerUsageSnapshot;

class ResellerSnapshotService
{
    public function __construct(
        private ResellerUsageService $usage
    ) {
    }

    public function capture(
        Reseller $reseller
    ): ResellerUsageSnapshot {
        $data =
            $this->usage->snapshot(
                $reseller
            );

        return ResellerUsageSnapshot::query()
            ->updateOrCreate(
                [
                    'reseller_id' =>
                        $reseller->id,

                    'snapshot_date' =>
                        now(
                            $reseller->timezone
                            ?: 'Asia/Qatar'
                        )->toDateString(),
                ],
                [
                    'client_count' =>
                        $data[
                            'used_clients'
                        ],

                    'operator_count' =>
                        $data[
                            'operators'
                        ],

                    'router_count' =>
                        $data[
                            'routers'
                        ],

                    'hotspot_voucher_count' =>
                        $data[
                            'hotspot_vouchers'
                        ],

                    'wallet_balance' =>
                        $data[
                            'wallet_balance'
                        ],
                ]
            );
    }

    public function captureAll(): int
    {
        $count = 0;

        Reseller::query()
            ->orderBy('id')
            ->each(
                function (
                    Reseller $reseller
                ) use (&$count): void {
                    $this->capture(
                        $reseller
                    );

                    $count++;
                }
            );

        return $count;
    }
}
