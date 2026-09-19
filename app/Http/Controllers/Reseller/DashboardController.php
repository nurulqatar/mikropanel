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
use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPlan;
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

        /*
         * RESELLER_SELF_UPGRADE_DATA_V1
         */
        $currentSubscription =
            $reseller
                ->activeSubscription;

        $currentClientLimit =
            (int) (
                $currentSubscription
                    ?->client_limit
                ?? 0
            );

        $upgradePlans =
            $user->isResellerOwner()
                ? ResellerPlan::query()
                    ->where(
                        'active',
                        true
                    )
                    ->where(
                        'price',
                        '>',
                        0
                    )
                    ->where(
                        'client_limit',
                        '>',
                        $currentClientLimit
                    )
                    ->orderBy(
                        'client_limit'
                    )
                    ->get([
                        'id',
                        'name',
                        'code',
                        'client_limit',
                        'price',
                        'validity_days',
                    ])
                    ->map(
                        fn (
                            ResellerPlan $plan
                        ): array => [
                            'id' =>
                                $plan->id,
                            'name' =>
                                $plan->name,
                            'code' =>
                                $plan->code,
                            'client_limit' =>
                                (int)
                                $plan->client_limit,
                            'price' =>
                                (float)
                                $plan->price,
                            'validity_days' =>
                                (int)
                                $plan->validity_days,
                        ]
                    )
                    ->values()
                : collect();

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

                    'current_plan' =>
                        $currentSubscription
                            ? [
                                'id' =>
                                    $currentSubscription
                                        ->reseller_plan_id,

                                'name' =>
                                    $currentSubscription
                                        ->plan
                                        ?->name,

                                'client_limit' =>
                                    (int)
                                    $currentSubscription
                                        ->client_limit,

                                'price' =>
                                    (float)
                                    $currentSubscription
                                        ->price,

                                'expires_at' =>
                                    $currentSubscription
                                        ->expires_at
                                        ?->timezone(
                                            $reseller->timezone
                                            ?: 'Asia/Qatar'
                                        )
                                        ->format(
                                            'Y-m-d H:i'
                                        ),
                            ]
                            : null,
                ],

                'upgradePlans' =>
                    $upgradePlans,

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

                    'online_clients' =>
                        Client::query()
                            ->where(
                                'connected',
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

                'pos' => [
                    'can_renew' =>
                        $user->hasPermission(
                            'clients.renew'
                        ),

                    'can_receive_payment' =>
                        $user->hasPermission(
                            'payments.manage'
                        ),

                    'clients' =>
                        Client::query()
                            ->with([
                                'package:id,name,price,validity_days',
                            ])
                            ->withSum(
                                [
                                    'invoices as total_due' =>
                                        function ($query) {
                                            $query->where(
                                                'status',
                                                '!=',
                                                'cancelled'
                                            );
                                        },
                                ],
                                'due_amount'
                            )
                            ->orderBy('name')
                            ->get()
                            ->map(
                                function (
                                    Client $client
                                ): array {
                                    return [
                                        'id' =>
                                            $client->id,

                                        'name' =>
                                            $client->name,

                                        'client_code' =>
                                            $client->client_code,

                                        'phone' =>
                                            $client->phone,

                                        'mac_address' =>
                                            $client->active_mac_address
                                            ?: $client->mac_address,

                                        'ip_address' =>
                                            $client->ip_address,

                                        'enabled' =>
                                            (bool)
                                            $client->enabled,

                                        'expiry_date' =>
                                            $client->expiry_date
                                                ?->format(
                                                    'Y-m-d'
                                                ),

                                        'zone_id' =>
                                            $client->zone_id,

                                        'package_id' =>
                                            $client->package_id,

                                        'package' =>
                                            $client->package
                                                ? [
                                                    'id' =>
                                                        $client
                                                            ->package
                                                            ->id,

                                                    'name' =>
                                                        $client
                                                            ->package
                                                            ->name,

                                                    'price' =>
                                                        (float)
                                                        $client
                                                            ->package
                                                            ->price,

                                                    'validity_days' =>
                                                        (int)
                                                        $client
                                                            ->package
                                                            ->validity_days,
                                                ]
                                                : null,

                                        'total_due' =>
                                            round(
                                                (float) (
                                                    $client
                                                        ->total_due
                                                    ?? 0
                                                ),
                                                2
                                            ),
                                    ];
                                }
                            )
                            ->values(),

                    'packages' =>
                        Package::query()
                            ->where(
                                'enabled',
                                true
                            )
                            ->orderBy('name')
                            ->get([
                                'id',
                                'zone_id',
                                'name',
                                'price',
                                'validity_days',
                            ])
                            ->map(
                                function (
                                    Package $package
                                ): array {
                                    return [
                                        'id' =>
                                            $package->id,

                                        'zone_id' =>
                                            $package->zone_id,

                                        'name' =>
                                            $package->name,

                                        'price' =>
                                            (float)
                                            $package->price,

                                        'validity_days' =>
                                            (int)
                                            $package
                                                ->validity_days,
                                    ];
                                }
                            )
                            ->values(),
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
