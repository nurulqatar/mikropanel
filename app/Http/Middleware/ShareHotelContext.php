<?php

namespace App\Http\Middleware;

use App\Services\Hotel\HotelEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareHotelContext
{
    public function __construct(
        private HotelEntitlementService $entitlements
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user =
            Auth::guard('hotel')
                ->user();

        if ($user && $user->hotel) {
            $hotel =
                $user->hotel;

            $logoUrl =
                $hotel->logo_path
                && Storage::disk('public')
                    ->exists(
                        $hotel->logo_path
                    )
                    ? Storage::disk('public')
                        ->url(
                            $hotel->logo_path
                        )
                    : null;

            $backgroundUrl =
                $hotel->background_path
                && Storage::disk('public')
                    ->exists(
                        $hotel->background_path
                    )
                    ? Storage::disk('public')
                        ->url(
                            $hotel->background_path
                        )
                    : null;

            Inertia::share(
                'hotelAuth',
                [
                    'user' => [
                        'id' =>
                            $user->id,

                        'name' =>
                            $user->name,

                        'email' =>
                            $user->email,

                        'role' =>
                            $user->role,

                        'permissions' =>
                            $user->permissions
                            ?? [],
                    ],

                    'hotel' => [
                        'id' =>
                            $hotel->id,

                        'code' =>
                            $hotel->code,

                        'name' =>
                            $hotel->name,

                        'timezone' =>
                            $hotel->timezone,

                        'currency' =>
                            $hotel->currency,

                        'logo_url' =>
                            $logoUrl,

                        'background_url' =>
                            $backgroundUrl,
                    ],

                    'subscription' =>
                        $this->entitlements
                            ->snapshot(
                                $hotel
                            ),
                ]
            );
        }

        return $next(
            $request
        );
    }
}
