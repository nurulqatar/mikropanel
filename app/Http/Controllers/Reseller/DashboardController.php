<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Expense;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerNotification;
use App\Models\Router;
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
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->isResellerUser(),
            403
        );

        $reseller =
            Reseller::query()
                ->with(
                    'activeSubscription.plan'
                )
                ->findOrFail(
                    $user->reseller_id
                );

        $today =
            Carbon::now(
                $reseller->timezone
                ?: 'Asia/Qatar'
            );

        $normalCollection =
            (float)
            Payment::query()
                ->whereDate(
                    'payment_date',
                    $today
                        ->toDateString()
                )
                ->sum(
                    'amount'
                );

        $hotspotCollection =
            (float)
            HotspotPayment::query()
                ->whereDate(
                    'payment_date',
                    $today
                        ->toDateString()
                )
                ->sum(
                    'amount'
                );

        return Inertia::render(
            'Reseller/Dashboard',
            [
                'reseller' => [
                    'id' =>
                        $reseller->id,

                    'code' =>
                        $reseller->code,

                    'company_name' =>
                        $reseller
                            ->company_name,

                    'status' =>
                        $reseller->status,

                    'expiry_mode' =>
                        $reseller
                            ->expiry_mode,

                    'wallet_balance' =>
                        (float)
                        $reseller
                            ->wallet_balance,

                    'plan' =>
                        $reseller
                            ->activeSubscription
                            ?->plan
                            ?->name,
                ],

                'usage' =>
                    $usage->snapshot(
                        $reseller
                    ),

                'stats' => [
                    'clients' =>
                        Client::query()
                            ->count(),

                    'active_clients' =>
                        Client::query()
                            ->where(
                                'enabled',
                                true
                            )
                            ->count(),

                    'routers' =>
                        Router::query()
                            ->count(),

                    'normal_due' =>
                        round(
                            (float)
                            Invoice::query()
                                ->where(
                                    'status',
                                    '!=',
                                    'cancelled'
                                )
                                ->sum(
                                    'due_amount'
                                ),
                            2
                        ),

                    'hotspot_due' =>
                        round(
                            (float)
                            HotspotInvoice::query()
                                ->where(
                                    'status',
                                    '!=',
                                    'cancelled'
                                )
                                ->sum(
                                    'due_amount'
                                ),
                            2
                        ),

                    'today_collection' =>
                        round(
                            $normalCollection
                            + $hotspotCollection,
                            2
                        ),

                    'expenses' =>
                        round(
                            (float)
                            Expense::query()
                                ->whereDate(
                                    'expense_date',
                                    $today
                                        ->toDateString()
                                )
                                ->sum(
                                    'amount'
                                ),
                            2
                        ),

                    'hotspot_vouchers' =>
                        HotspotVoucher::query()
                            ->count(),

                    'online_hotspot' =>
                        HotspotSession::query()
                            ->where(
                                'active',
                                true
                            )
                            ->count(),

                    'unread_notifications' =>
                        ResellerNotification::query()
                            ->whereNull(
                                'read_at'
                            )
                            ->count(),
                ],

                'recentClients' =>
                    Client::query()
                        ->with([
                            'package:id,name',
                            'router:id,name',
                        ])
                        ->latest('id')
                        ->limit(8)
                        ->get([
                            'id',
                            'name',
                            'client_code',
                            'ip_address',
                            'enabled',
                            'expiry_date',
                            'package_id',
                            'router_id',
                        ]),

                'isOwner' =>
                    $user
                        ->isResellerOwner(),
            ]
        );
    }
}
