<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelVoucher;
use App\Services\Hotel\HotelEntitlementService;
use App\Services\Hotel\HotelGuestVoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function welcome(
        Request $request,
        Hotel $hotel,
        HotelEntitlementService $entitlements
    ): Response {
        $this->available(
            $hotel,
            $entitlements
        );

        return Inertia::render(
            'Hotel/Portal/Welcome',
            $this->portalData(
                $request,
                $hotel
            )
        );
    }

    public function access(
        Request $request,
        Hotel $hotel,
        HotelEntitlementService $entitlements
    ): Response {
        $this->available(
            $hotel,
            $entitlements
        );

        return Inertia::render(
            'Hotel/Portal/Access',
            $this->portalData(
                $request,
                $hotel
            )
        );
    }

    public function register(
        Request $request,
        Hotel $hotel,
        HotelEntitlementService $entitlements
    ): Response {
        $this->available(
            $hotel,
            $entitlements
        );

        return Inertia::render(
            'Hotel/Portal/Register',
            $this->portalData(
                $request,
                $hotel
            )
        );
    }

    public function store(
        Request $request,
        Hotel $hotel,
        HotelGuestVoucherService $service
    ) {
        $data =
            $this->guestData(
                $request
            );

        $voucher =
            $service->issue(
                hotel:
                    $hotel,

                data:
                    $data,

                creator:
                    null,

                source:
                    'portal',

                ipAddress:
                    $request->ip()
            );

        return redirect()
            ->route(
                'hotel.portal.voucher',
                [
                    'hotel' =>
                        $hotel->slug,

                    'token' =>
                        $voucher
                            ->public_token,

                    'lang' =>
                        $data[
                            'preferred_locale'
                        ],
                ]
            );
    }

    public function voucher(
        Request $request,
        Hotel $hotel,
        string $token
    ): Response {
        $voucher =
            HotelVoucher::query()
                ->with([
                    'stay.guest',
                    'profile',
                ])
                ->where(
                    'hotel_id',
                    $hotel->id
                )
                ->where(
                    'public_token',
                    $token
                )
                ->firstOrFail();

        return Inertia::render(
            'Hotel/Portal/Voucher',
            [
                ...$this->portalData(
                    $request,
                    $hotel
                ),

                'voucher' => [
                    'username' =>
                        $voucher->username,

                    'expires_at' =>
                        $voucher
                            ->expires_at
                            ->timezone(
                                $hotel
                                    ->timezone
                            )
                            ->format(
                                'Y-m-d H:i'
                            ),

                    'room_number' =>
                        $voucher
                            ->stay
                            ->room_number,

                    'guest_name' =>
                        $voucher
                            ->stay
                            ->guest
                            ->name,

                    'status' =>
                        $voucher
                            ->status,
                ],
            ]
        );
    }

    public function login(
        Request $request,
        Hotel $hotel
    ): Response {
        return Inertia::render(
            'Hotel/Portal/Login',
            [
                ...$this->portalData(
                    $request,
                    $hotel
                ),

                'verification' =>
                    null,
            ]
        );
    }

    public function verify(
        Request $request,
        Hotel $hotel
    ): Response {
        $data =
            $request->validate([
                'voucher_code' => [
                    'required',
                    'string',
                    'max:50',
                ],
            ]);

        $code =
            strtoupper(
                trim(
                    $data[
                        'voucher_code'
                    ]
                )
            );

        $voucher =
            HotelVoucher::query()
                ->with([
                    'stay.guest',
                ])
                ->where(
                    'hotel_id',
                    $hotel->id
                )
                ->where(
                    'username',
                    $code
                )
                ->whereIn(
                    'status',
                    [
                        'unused',
                        'active',
                    ]
                )
                ->where(
                    'expires_at',
                    '>',
                    now()
                )
                ->first();

        return Inertia::render(
            'Hotel/Portal/Login',
            [
                ...$this->portalData(
                    $request,
                    $hotel
                ),

                'verification' =>
                    $voucher
                        ? [
                            'valid' =>
                                true,

                            'voucher_code' =>
                                $voucher
                                    ->username,

                            'guest_name' =>
                                $voucher
                                    ->stay
                                    ->guest
                                    ->name,

                            'room_number' =>
                                $voucher
                                    ->stay
                                    ->room_number,

                            'expires_at' =>
                                $voucher
                                    ->expires_at
                                    ->timezone(
                                        $hotel
                                            ->timezone
                                    )
                                    ->format(
                                        'Y-m-d H:i'
                                    ),
                        ]
                        : [
                            'valid' =>
                                false,
                        ],
            ]
        );
    }

    private function guestData(
        Request $request
    ): array {
        $data =
            $request->validate([
                'room_number' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'check_in_date' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'check_out_date' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'phone' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^\+?[0-9][0-9\s\-\(\)]{5,30}$/',
                ],

                'phone_country' => [
                    'nullable',
                    'string',
                    'max:10',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'identity_type' => [
                    'required',
                    Rule::in([
                        'passport',
                        'qid',
                    ]),
                ],

                'identity_number' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'nationality' => [
                    'required',
                    'string',
                    'max:120',
                ],

                'preferred_locale' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'terms_accepted' => [
                    'accepted',
                ],
            ]);

        $data[
            'preferred_locale'
        ] =
            $data[
                'preferred_locale'
            ]
            ?? 'en';

        return $data;
    }

    private function available(
        Hotel $hotel,
        HotelEntitlementService $entitlements
    ): void {
        abort_unless(
            $hotel->status
                === 'active',
            404
        );

        abort_unless(
            $entitlements
                ->usable(
                    $hotel
                ),
            403,
            'Hotel WiFi service is currently unavailable.'
        );
    }

    private function portalData(
        Request $request,
        Hotel $hotel
    ): array {
        $languages =
            $hotel->enabled_locales
            ?: [
                'en',
                'ar',
                'bn',
                'hi',
                'ur',
                'ne',
                'tl',
                'zh',
                'fr',
                'es',
            ];

        $locale =
            (string)
            $request->query(
                'lang',
                $hotel
                    ->default_locale
                ?: 'en'
            );

        if (
            !in_array(
                $locale,
                $languages,
                true
            )
        ) {
            $locale =
                $hotel
                    ->default_locale
                ?: 'en';
        }

        return [
            'hotel' => [
                'name' =>
                    $hotel->name,

                'slug' =>
                    $hotel->slug,

                'portal_title' =>
                    $hotel
                        ->portal_title,

                'portal_subtitle' =>
                    $hotel
                        ->portal_subtitle,

                'phone' =>
                    $hotel->phone,

                'email' =>
                    $hotel->email,

                'whatsapp' =>
                    $hotel->whatsapp,

                'address' =>
                    $hotel->address,

                'primary_color' =>
                    $hotel
                        ->primary_color,

                'secondary_color' =>
                    $hotel
                        ->secondary_color,

                'terms_text' =>
                    $hotel
                        ->terms_text,

                'privacy_text' =>
                    $hotel
                        ->privacy_text,

                'logo_url' =>
                    $this->storageUrl(
                        $hotel
                            ->logo_path
                    ),

                'background_url' =>
                    $this->storageUrl(
                        $hotel
                            ->background_path
                    ),
            ],

            'locale' =>
                $locale,

            'languages' =>
                array_values(
                    $languages
                ),
        ];
    }

    private function storageUrl(
        ?string $path
    ): ?string {
        if (
            !$path
            || !Storage::disk('public')
                ->exists(
                    $path
                )
        ) {
            return null;
        }

        return Storage::disk('public')
            ->url(
                $path
            );
    }
}
