<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
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
            ]
        );
    }
}
