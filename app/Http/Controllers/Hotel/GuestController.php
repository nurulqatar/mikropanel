<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelStay;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(): Response
    {
        $user =
            Auth::guard('hotel')
                ->user();

        abort_unless(
            $user
            && (
                $user->isAdmin()
                || $user->hasPermission(
                    'guests.manage'
                )
                || $user->hasPermission(
                    'reports.view'
                )
            ),
            403
        );

        $hotel =
            $user->hotel;

        return Inertia::render(
            'Hotel/Guests/Index',
            [
                'stays' =>
                    HotelStay::query()
                        ->with([
                            'guest',
                            'voucher',
                        ])
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->latest('id')
                        ->limit(500)
                        ->get(),
            ]
        );
    }
}
