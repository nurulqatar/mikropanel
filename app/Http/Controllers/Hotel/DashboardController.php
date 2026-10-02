<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelStay;
use App\Models\Hotel\HotelVoucher;
use App\Services\Hotel\HotelEntitlementService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        HotelEntitlementService $entitlements
    ): Response {
        $user =
            Auth::guard('hotel')
                ->user();

        $hotel =
            $user->hotel;

        return Inertia::render(
            'Hotel/Dashboard',
            [
                'usage' =>
                    $entitlements
                        ->snapshot(
                            $hotel
                        ),

                'stats' => [
                    'active_stays' =>
                        HotelStay::query()
                            ->where(
                                'hotel_id',
                                $hotel->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->where(
                                'check_out_at',
                                '>',
                                now()
                            )
                            ->count(),

                    'active_vouchers' =>
                        HotelVoucher::query()
                            ->where(
                                'hotel_id',
                                $hotel->id
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
                            ->count(),
                ],
            ]
        );
    }
}
