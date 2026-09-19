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

    public function clientUnlimited(
        Reseller $reseller
    ): bool {
        /*
         * An explicit Company override always wins.
         * If Super Admin sets a finite override on an
         * Unlimited plan, that Company becomes finite.
         */
        if (
            $reseller
                ->client_limit_override
            !== null
        ) {
            return false;
        }

        return (bool) (
            $this->subscription(
                $reseller
            )?->is_unlimited
            ?? false
        );
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

        if (
            $this->clientUnlimited(
                $reseller
            )
        ) {
            /*
             * Compatibility return value for older
             * reporting code. Enforcement bypasses
             * this numeric value when Unlimited.
             */
            return 1000000;
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
        if (
            $this->clientUnlimited(
                $reseller
            )
        ) {
            return PHP_INT_MAX;
        }

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
        if (
            $reseller->status
            !== 'active'
            || !$this
                ->subscriptionIsUsable(
                    $reseller
                )
        ) {
            return false;
        }

        return $this->clientUnlimited(
            $reseller
        )
            || $this
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

        $unlimited =
            $this->clientUnlimited(
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

            'is_unlimited' =>
                $unlimited,

            'used_clients' =>
                $used,

            'remaining_clients' =>
                $unlimited
                    ? PHP_INT_MAX
                    : max(
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

    public function routerLimit(
        Reseller $reseller
    ): int {
        if (
            $reseller->router_limit_override
            !== null
        ) {
            return max(
                0,
                (int)
                $reseller->router_limit_override
            );
        }

        return max(
            0,
            (int) (
                $this->subscription(
                    $reseller
                )?->router_limit
                ?? 0
            )
        );
    }

    public function usedRouterSlots(
        Reseller $reseller
    ): int {
        return Router::query()
            ->where(
                'reseller_id',
                $reseller->id
            )
            ->count();
    }

    public function remainingRouterSlots(
        Reseller $reseller
    ): int {
        return max(
            0,
            $this->routerLimit(
                $reseller
            )
            - $this->usedRouterSlots(
                $reseller
            )
        );
    }

    public function operatorLimit(
        Reseller $reseller
    ): int {
        if (
            $reseller->operator_limit_override
            !== null
        ) {
            return max(
                0,
                (int)
                $reseller->operator_limit_override
            );
        }

        return max(
            0,
            (int) (
                $this->subscription(
                    $reseller
                )?->operator_limit
                ?? 0
            )
        );
    }

    public function usedOperatorSlots(
        Reseller $reseller
    ): int {
        return User::query()
            ->where(
                'reseller_id',
                $reseller->id
            )
            ->where(
                'role',
                'operator'
            )
            ->count();
    }

    public function remainingOperatorSlots(
        Reseller $reseller
    ): int {
        return max(
            0,
            $this->operatorLimit(
                $reseller
            )
            - $this->usedOperatorSlots(
                $reseller
            )
        );
    }
}
