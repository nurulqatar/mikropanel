<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelGuest;
use App\Models\Hotel\HotelStay;
use App\Models\Hotel\HotelSubscription;
use App\Models\Hotel\HotelUser;
use App\Models\Hotel\HotelVoucher;
use App\Models\Hotel\HotelWifiProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HotelGuestVoucherService
{
    public function issue(
        Hotel $hotel,
        array $data,
        ?HotelUser $creator = null,
        string $source = 'portal',
        ?string $ipAddress = null
    ): HotelVoucher {
        return DB::transaction(
            function () use (
                $hotel,
                $data,
                $creator,
                $source,
                $ipAddress
            ): HotelVoucher {
                $subscription =
                    HotelSubscription::query()
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                if (
                    !$subscription
                    || $hotel->status
                        !== 'active'
                    || $subscription
                        ->starts_at
                        ->gt(now())
                    || $subscription
                        ->expires_at
                        ->lte(now())
                ) {
                    throw ValidationException::withMessages([
                        'subscription' =>
                            'Hotel subscription is inactive or expired.',
                    ]);
                }

                if (
                    !$subscription
                        ->is_guest_unlimited
                ) {
                    $used =
                        HotelStay::query()
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

                    if (
                        $used >=
                        (int) (
                            $subscription
                                ->guest_limit
                            ?? 0
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'guest_limit' =>
                                'Hotel guest registration limit has been reached.',
                        ]);
                    }
                }

                $timezone =
                    $hotel->timezone
                    ?: 'Asia/Qatar';

                $checkoutClock =
                    substr(
                        (string)
                        $hotel
                            ->check_out_time,
                        0,
                        8
                    );

                if (
                    strlen(
                        $checkoutClock
                    ) === 5
                ) {
                    $checkoutClock .=
                        ':00';
                }

                $checkIn =
                    CarbonImmutable::createFromFormat(
                        'Y-m-d H:i:s',
                        $data[
                            'check_in_date'
                        ]
                        . ' 00:00:00',
                        $timezone
                    )->utc();

                $checkOut =
                    CarbonImmutable::createFromFormat(
                        'Y-m-d H:i:s',
                        $data[
                            'check_out_date'
                        ]
                        . ' '
                        . $checkoutClock,
                        $timezone
                    )->utc();

                if (
                    $checkOut
                        ->lte($checkIn)
                ) {
                    throw ValidationException::withMessages([
                        'check_out_date' =>
                            'Checkout date must be after check-in date.',
                    ]);
                }

                if (
                    $checkOut
                        ->lte(
                            now()->utc()
                        )
                ) {
                    throw ValidationException::withMessages([
                        'check_out_date' =>
                            'Checkout time must be in the future.',
                    ]);
                }

                $identityType =
                    strtolower(
                        trim(
                            $data[
                                'identity_type'
                            ]
                        )
                    );

                $identity =
                    strtoupper(
                        preg_replace(
                            '/[^A-Za-z0-9]/',
                            '',
                            (string)
                            $data[
                                'identity_number'
                            ]
                        )
                    );

                if ($identity === '') {
                    throw ValidationException::withMessages([
                        'identity_number' =>
                            'A valid identification number is required.',
                    ]);
                }

                $identityHash =
                    hash(
                        'sha256',
                        $hotel->id
                        . '|'
                        . $identityType
                        . '|'
                        . $identity
                    );

                $guest =
                    HotelGuest::query()
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->where(
                            'identity_hash',
                            $identityHash
                        )
                        ->first();

                $guestData = [
                    'hotel_id' =>
                        $hotel->id,

                    'name' =>
                        trim(
                            $data['name']
                        ),

                    'phone' =>
                        trim(
                            $data['phone']
                        ),

                    'phone_country' =>
                        $data[
                            'phone_country'
                        ]
                        ?? null,

                    'nationality' =>
                        trim(
                            $data[
                                'nationality'
                            ]
                        ),

                    'identity_type' =>
                        $identityType,

                    'identity_number' =>
                        $identity,

                    'identity_hash' =>
                        $identityHash,

                    'preferred_locale' =>
                        $data[
                            'preferred_locale'
                        ]
                        ?? 'en',

                    'consent_at' =>
                        now(),
                ];

                if ($guest) {
                    $guest->update(
                        $guestData
                    );
                } else {
                    $guest =
                        HotelGuest::query()
                            ->create(
                                $guestData
                            );
                }

                $stay =
                    HotelStay::query()
                        ->create([
                            'hotel_id' =>
                                $hotel->id,

                            'hotel_guest_id' =>
                                $guest->id,

                            'room_number' =>
                                trim(
                                    $data[
                                        'room_number'
                                    ]
                                ),

                            'check_in_at' =>
                                $checkIn,

                            'check_out_at' =>
                                $checkOut,

                            'status' =>
                                'active',

                            'registration_source' =>
                                $source,

                            'created_by' =>
                                $creator?->id,

                            'registration_ip' =>
                                $ipAddress,
                        ]);

                $profile =
                    HotelWifiProfile::query()
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderByDesc(
                            'is_default'
                        )
                        ->orderBy('id')
                        ->first();

                if (!$profile) {
                    $profile =
                        HotelWifiProfile::query()
                            ->create([
                                'hotel_id' =>
                                    $hotel->id,

                                'name' =>
                                    'Guest WiFi',

                                'code' =>
                                    'GUEST',

                                'shared_users' =>
                                    1,

                                'is_default' =>
                                    true,

                                'enabled' =>
                                    true,
                            ]);
                }

                $code =
                    $this
                        ->generateCode();

                return HotelVoucher::query()
                    ->create([
                        'hotel_id' =>
                            $hotel->id,

                        'hotel_stay_id' =>
                            $stay->id,

                        'hotel_wifi_profile_id' =>
                            $profile->id,

                        'username' =>
                            $code,

                        'password' =>
                            $code,

                        'public_token' =>
                            Str::random(64),

                        'status' =>
                            'unused',

                        'expires_at' =>
                            $checkOut,

                        'created_by' =>
                            $creator?->id,

                        'creation_source' =>
                            $source,
                    ]);
            }
        );
    }

    private function generateCode(): string
    {
        $alphabet =
            'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for (
                $i = 0;
                $i < 8;
                $i++
            ) {
                $code .=
                    $alphabet[
                        random_int(
                            0,
                            strlen(
                                $alphabet
                            ) - 1
                        )
                    ];
            }
        } while (
            HotelVoucher::withTrashed()
                ->where(
                    'username',
                    $code
                )
                ->exists()
        );

        return $code;
    }
}
