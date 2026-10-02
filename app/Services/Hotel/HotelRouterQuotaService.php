<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelSubscription;
use Illuminate\Validation\ValidationException;

class HotelRouterQuotaService
{
    public function subscription(
        Hotel $hotel,
        bool $lock = false
    ): ?HotelSubscription {
        $query =
            HotelSubscription::query()
                ->where(
                    'hotel_id',
                    $hotel->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function usable(
        Hotel $hotel,
        ?HotelSubscription $subscription = null
    ): bool {
        $subscription ??=
            $this->subscription(
                $hotel
            );

        if (
            !$subscription
            || $hotel->status
                !== 'active'
        ) {
            return false;
        }

        if (
            $subscription->starts_at
            && $subscription
                ->starts_at
                ->gt(now())
        ) {
            return false;
        }

        if (
            !$subscription->expires_at
            || $subscription
                ->expires_at
                ->lte(now())
        ) {
            return false;
        }

        return true;
    }

    public function usage(
        Hotel $hotel
    ): int {
        return HotelRouter::query()
            ->where(
                'hotel_id',
                $hotel->id
            )
            ->count();
    }

    public function canAdd(
        Hotel $hotel
    ): bool {
        $subscription =
            $this->subscription(
                $hotel
            );

        if (
            !$this->usable(
                $hotel,
                $subscription
            )
        ) {
            return false;
        }

        if (
            $subscription
                ->is_router_unlimited
        ) {
            return true;
        }

        return $this->usage(
            $hotel
        ) < (int) (
            $subscription
                ->router_limit
            ?? 0
        );
    }

    public function assertCanAddForUpdate(
        Hotel $hotel
    ): HotelSubscription {
        $subscription =
            $this->subscription(
                $hotel,
                true
            );

        if (
            !$this->usable(
                $hotel,
                $subscription
            )
        ) {
            throw ValidationException::withMessages([
                'router' =>
                    'Hotel subscription is inactive or expired.',
            ]);
        }

        if (
            !$subscription
                ->is_router_unlimited
        ) {
            $usage =
                $this->usage(
                    $hotel
                );

            if (
                $usage
                >= (int) (
                    $subscription
                        ->router_limit
                    ?? 0
                )
            ) {
                throw ValidationException::withMessages([
                    'router' =>
                        'MikroTik router limit has been reached.',
                ]);
            }
        }

        return $subscription;
    }

    public function snapshot(
        Hotel $hotel
    ): array {
        $subscription =
            $this->subscription(
                $hotel
            );

        return [
            'usable' =>
                $this->usable(
                    $hotel,
                    $subscription
                ),

            'router_usage' =>
                $this->usage(
                    $hotel
                ),

            'router_limit' =>
                $subscription
                    ?->router_limit,

            'router_unlimited' =>
                (bool) (
                    $subscription
                        ?->is_router_unlimited
                    ?? false
                ),

            'subscription_expires_at' =>
                $subscription
                    ?->expires_at
                    ?->toISOString(),
        ];
    }
}
