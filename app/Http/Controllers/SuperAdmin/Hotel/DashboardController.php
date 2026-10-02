<?php

namespace App\Http\Controllers\SuperAdmin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelPlan;
use App\Models\Hotel\HotelSubscription;
use App\Models\Hotel\HotelUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request
    ): Response {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->is_active
            && $request
                ->user()
                ->isSuperAdmin(),
            403
        );

        return Inertia::render(
            'SuperAdmin/HotelHotspot/Dashboard',
            [
                'stats' => [
                    'hotels' =>
                        Hotel::query()
                            ->count(),

                    'active_hotels' =>
                        Hotel::query()
                            ->where(
                                'status',
                                'active'
                            )
                            ->count(),

                    'plans' =>
                        HotelPlan::query()
                            ->where(
                                'active',
                                true
                            )
                            ->count(),

                    'active_subscriptions' =>
                        HotelSubscription::query()
                            ->where(
                                'status',
                                'active'
                            )
                            ->where(
                                'expires_at',
                                '>',
                                now()
                            )
                            ->count(),

                    'hotel_users' =>
                        HotelUser::query()
                            ->count(),
                ],

                'recentHotels' =>
                    Hotel::query()
                        ->with([
                            'activeSubscription.plan',
                        ])
                        ->latest('id')
                        ->limit(10)
                        ->get(),
            ]
        );
    }
}
