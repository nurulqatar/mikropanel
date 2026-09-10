<?php

namespace App\Services\Reseller;

use App\Models\Client;
use App\Models\HotspotVoucher;
use App\Models\Reseller;
use App\Models\ResellerSubscription;
use App\Models\Router;
use App\Models\User;
use Carbon\Carbon;

class ResellerUsageService
{
    public function subscription(
        Reseller $reseller
    ): ?ResellerSubscription {
        return ResellerSubscription::query()
            ->where(
                'reseller_id',
                $reseller->id
            )
            ->where(
                'status',
                'active'
            )
            ->latest('id')
            ->first();
    }

    public function subscriptionIsUsable(
        Reseller $reseller
    ): bool {
        $subscription =
            $this->subscription(
                $reseller
            );

        if (!$subscription) {
            return false;
        }

        $now = Carbon::now(
            $reseller->timezone
            ?: 'Asia/Qatar'
        );

        if (
            $subscription->expires_at
            && $subscription
                ->expires_at
                ->gte($now)
        ) {
            return true;
        }

        return $subscription
            ->grace_until
            && $subscription
                ->grace_until
                ->gte($now);
    }

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
            $this->subscription(
                $reseller
            );

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
         * Soft-deleted archived clients
         * do not consume a slot.
         * Suspended clients still do.
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
        return $reseller->status === 'active'
            && $this
                ->subscriptionIsUsable(
                    $reseller
                )
            && $this
                ->remainingClientSlots(
                    $reseller
                ) > 0;
    }

    public function snapshot(
        Reseller $reseller
    ): array {
        $subscription =
            $this->subscription(
                $reseller
            );

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

            'subscription_usable' =>
                $this
                    ->subscriptionIsUsable(
                        $reseller
                    ),

            'subscription_expires_at' =>
                $subscription
                    ?->expires_at
                    ?->format(
                        'Y-m-d H:i:s'
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
