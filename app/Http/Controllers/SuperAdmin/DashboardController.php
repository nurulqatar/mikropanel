<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Reseller;
use App\Models\ResellerSubscription;
use App\Services\Reseller\ResellerUsageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ResellerUsageService $usage
    ): Response {
        $this->superAdmin(
            $request
        );

        $resellers =
            Reseller::query()
                ->with([
                    'activeSubscription.plan',
                ])
                ->orderByDesc('id')
                ->get();

        $totalSlots = 0;

        foreach (
            $resellers
            as $reseller
        ) {
            $totalSlots +=
                $usage->clientLimit(
                    $reseller
                );
        }

        $now = Carbon::now(
            'Asia/Qatar'
        );

        $sevenDays =
            $now
                ->copy()
                ->addDays(7);

        return Inertia::render(
            'SuperAdmin/Dashboard',
            [
                'stats' => [
                    'total_resellers' =>
                        $resellers->count(),

                    'active_resellers' =>
                        $resellers
                            ->where(
                                'status',
                                'active'
                            )
                            ->count(),

                    'suspended_resellers' =>
                        $resellers
                            ->where(
                                'status',
                                'suspended'
                            )
                            ->count(),

                    'total_client_slots' =>
                        $totalSlots,

                    'used_client_slots' =>
                        Client::query()
                            ->whereNotNull(
                                'reseller_id'
                            )
                            ->count(),

                    'wallet_balance' =>
                        round(
                            (float)
                            $resellers
                                ->sum(
                                    'wallet_balance'
                                ),
                            2
                        ),

                    'expiring_7_days' =>
                        ResellerSubscription::query()
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereBetween(
                                'expires_at',
                                [
                                    $now,
                                    $sevenDays,
                                ]
                            )
                            ->count(),

                    'expired_subscriptions' =>
                        ResellerSubscription::query()
                            ->where(
                                'status',
                                'active'
                            )
                            ->where(
                                'expires_at',
                                '<',
                                $now
                            )
                            ->count(),
                ],

                'recentResellers' =>
                    $resellers
                        ->take(8)
                        ->map(
                            function (
                                Reseller $reseller
                            ) use (
                                $usage
                            ): array {
                                return [
                                    'id' =>
                                        $reseller->id,

                                    'code' =>
                                        $reseller->code,

                                    'company_name' =>
                                        $reseller
                                            ->company_name,

                                    'owner_name' =>
                                        $reseller
                                            ->owner_name,

                                    'status' =>
                                        $reseller
                                            ->status,

                                    'wallet_balance' =>
                                        (float)
                                        $reseller
                                            ->wallet_balance,

                                    'usage' =>
                                        $usage
                                            ->snapshot(
                                                $reseller
                                            ),

                                    'plan' =>
                                        $reseller
                                            ->activeSubscription
                                            ?->plan
                                            ?->name,
                                ];
                            }
                        )
                        ->values(),
            ]
        );
    }

    private function superAdmin(
        Request $request
    ): void {
        $user = $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user->isSuperAdmin(),
            403
        );
    }
}
