<?php

namespace App\Services\Reseller;

use App\Models\Client;
use App\Models\HotspotVoucher;
use App\Models\Reseller;
use App\Models\Router;
use App\Models\User;

class ResellerUsageService
{
    public function clientLimit(
        Reseller $reseller
    ): int {
        if (
            $reseller
                ->client_limit_override
            !== null
        ) {
            return max(
                0,
                (int)
                $reseller
                    ->client_limit_override
            );
        }

        $subscription =
            $reseller
                ->activeSubscription()
                ->first();

        return $subscription
            ? max(
                0,
                (int)
                $subscription
                    ->client_limit
            )
            : 0;
    }

    public function usedClientSlots(
        Reseller $reseller
    ): int {
        /*
         * Client model uses SoftDeletes.
         * Archived clients therefore do not
         * consume a reseller client slot.
         * Suspended clients still consume one.
         */
        return Client::query()
            ->where(
                'reseller_id',
                $reseller->id
            )
            ->count();
    }

    public function remainingClientSlots(
        Reseller $reseller
    ): int {
        return max(
            0,
            $this->clientLimit(
                $reseller
            )
            - $this->usedClientSlots(
                $reseller
            )
        );
    }

    public function canCreateClient(
        Reseller $reseller
    ): bool {
        return $this
            ->remainingClientSlots(
                $reseller
            ) > 0;
    }

    public function snapshot(
        Reseller $reseller
    ): array {
        $limit =
            $this->clientLimit(
                $reseller
            );

        $used =
            $this->usedClientSlots(
                $reseller
            );

        return [
            'client_limit' =>
                $limit,

            'used_clients' =>
                $used,

            'remaining_clients' =>
                max(
                    0,
                    $limit - $used
                ),

            'operators' =>
                User::query()
                    ->where(
                        'reseller_id',
                        $reseller->id
                    )
                    ->where(
                        'role',
                        'operator'
                    )
                    ->count(),

            'routers' =>
                Router::query()
                    ->where(
                        'reseller_id',
                        $reseller->id
                    )
                    ->count(),

            'hotspot_vouchers' =>
                HotspotVoucher::query()
                    ->where(
                        'reseller_id',
                        $reseller->id
                    )
                    ->count(),

            'wallet_balance' =>
                (float)
                $reseller
                    ->wallet_balance,
        ];
    }
}
