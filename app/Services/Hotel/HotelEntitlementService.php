<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelSubscription;
use App\Models\Hotel\HotelStay;
use App\Models\Hotel\HotelUser;

class HotelEntitlementService
{
    public function subscription(
        Hotel $hotel
    ): ?HotelSubscription {
        return HotelSubscription::query()
            ->where(
                'hotel_id',
                $hotel->id
            )
            ->where(
                'status',
                'active'
            )
            ->latest('id')
            ->first();
    }

    public function usable(
        Hotel $hotel
    ): bool {
        if (
            $hotel->status
            !== 'active'
        ) {
            return false;
        }

        $subscription =
            $this->subscription(
                $hotel
            );

        if (!$subscription) {
            return false;
        }

        $now = now();

        return $subscription
            ->starts_at
            ->lte($now)
            && $subscription
                ->expires_at
                ->gt($now);
    }

    /*
     * HOTEL_GUEST_USAGE_V1
     */
    public function guestUsage(
        Hotel $hotel
    ): int {
        $subscription =
            $this->subscription(
                $hotel
            );

        if (!$subscription) {
            return 0;
        }

        return HotelStay::query()
            ->where(
                'hotel_id',
                $hotel->id
            )
            ->whereBetween(
                'created_at',
                [
                    $subscription
                        ->starts_at,

                    $subscription
                        ->expires_at,
                ]
            )
            ->count();
    }

    public function canRegisterGuest(
        Hotel $hotel
    ): bool {
        $subscription =
            $this->subscription(
                $hotel
            );

        if (
            !$subscription
            || !$this->usable(
                $hotel
            )
        ) {
            return false;
        }

        if (
            $subscription
                ->is_guest_unlimited
        ) {
            return true;
        }

        return $this
            ->guestUsage(
                $hotel
            )
            < (int) (
                $subscription
                    ->guest_limit
                ?? 0
            );
    }

    public function receptionistUsage(
        Hotel $hotel
    ): int {
        return HotelUser::query()
            ->where(
                'hotel_id',
                $hotel->id
            )
            ->where(
                'role',
                'receptionist'
            )
            ->count();
    }

    public function canAddReceptionist(
        Hotel $hotel
    ): bool {
        $subscription =
            $this->subscription(
                $hotel
            );

        if (
            !$subscription
            || !$this->usable(
                $hotel
            )
        ) {
            return false;
        }

        if (
            $subscription
                ->is_receptionist_unlimited
        ) {
            return true;
        }

        return $this
            ->receptionistUsage(
                $hotel
            )
            < (int) (
                $subscription
                    ->receptionist_limit
                ?? 0
            );
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
                    $hotel
                ),

            'guest_usage' =>
                $this->guestUsage(
                    $hotel
                ),

            'guest_limit' =>
                $subscription
                    ?->guest_limit,

            'guest_unlimited' =>
                (bool) (
                    $subscription
                        ?->is_guest_unlimited
                    ?? false
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

            'receptionist_limit' =>
                $subscription
                    ?->receptionist_limit,

            'receptionist_unlimited' =>
                (bool) (
                    $subscription
                        ?->is_receptionist_unlimited
                    ?? false
                ),

            'receptionist_usage' =>
                $this
                    ->receptionistUsage(
                        $hotel
                    ),

            'concurrent_limit' =>
                $subscription
                    ?->concurrent_limit,

            'expires_at' =>
                $subscription
                    ?->expires_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),
        ];
    }
}
