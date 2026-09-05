<?php

namespace App\Http\Controllers;

use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotPlan;
use App\Models\HotspotServer;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotspotSectionController extends Controller
{
    public function dashboard(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        $today = Carbon::now(
            'Asia/Qatar'
        );

        $monthStart =
            $today
                ->copy()
                ->startOfMonth()
                ->toDateString();

        $monthEnd =
            $today
                ->copy()
                ->endOfMonth()
                ->toDateString();

        return Inertia::render(
            'Hotspot/Dashboard',
            [
                'stats' => [
                    'servers' =>
                        HotspotServer::query()
                            ->count(),

                    'connected_servers' =>
                        HotspotServer::query()
                            ->where(
                                'connected',
                                true
                            )
                            ->count(),

                    'plans' =>
                        HotspotPlan::query()
                            ->where(
                                'enabled',
                                true
                            )
                            ->count(),

                    'unused_vouchers' =>
                        HotspotVoucher::query()
                            ->where(
                                'status',
                                'unused'
                            )
                            ->whereNull(
                                'sold_at'
                            )
                            ->count(),

                    'active_vouchers' =>
                        HotspotVoucher::query()
                            ->where(
                                'status',
                                'active'
                            )
                            ->count(),

                    'expired_vouchers' =>
                        HotspotVoucher::query()
                            ->where(
                                'status',
                                'expired'
                            )
                            ->count(),

                    'suspended_vouchers' =>
                        HotspotVoucher::query()
                            ->where(
                                'status',
                                'suspended'
                            )
                            ->count(),

                    'online_sessions' =>
                        HotspotSession::query()
                            ->where(
                                'active',
                                true
                            )
                            ->count(),

                    'total_due' =>
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
                            (float)
                            HotspotPayment::query()
                                ->whereDate(
                                    'payment_date',
                                    $today
                                        ->toDateString()
                                )
                                ->sum(
                                    'amount'
                                ),
                            2
                        ),

                    'month_collection' =>
                        round(
                            (float)
                            HotspotPayment::query()
                                ->whereBetween(
                                    'payment_date',
                                    [
                                        $monthStart,
                                        $monthEnd,
                                    ]
                                )
                                ->sum(
                                    'amount'
                                ),
                            2
                        ),
                ],

                'recentVouchers' =>
                    HotspotVoucher::query()
                        ->with([
                            'plan:id,name,price',
                            'server:id,name',
                        ])
                        ->latest('id')
                        ->limit(8)
                        ->get([
                            'id',
                            'hotspot_server_id',
                            'hotspot_plan_id',
                            'username',
                            'status',
                            'sold_at',
                            'activated_at',
                            'expires_at',
                        ]),

                'recentSessions' =>
                    HotspotSession::query()
                        ->where(
                            'active',
                            true
                        )
                        ->with(
                            'server:id,name'
                        )
                        ->orderByDesc(
                            'last_seen_at'
                        )
                        ->limit(8)
                        ->get(),

                'capabilities' =>
                    $this->capabilities(
                        $request
                    ),
            ]
        );
    }

    public function servers(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        return Inertia::render(
            'Hotspot/Servers',
            [
                'servers' =>
                    HotspotServer::query()
                        ->with(
                            'router:id,name'
                        )
                        ->orderBy('name')
                        ->get(),

                'capabilities' =>
                    $this->capabilities(
                        $request
                    ),
            ]
        );
    }

    public function plans(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        return Inertia::render(
            'Hotspot/Plans',
            [
                'plans' =>
                    HotspotPlan::query()
                        ->orderByDesc(
                            'enabled'
                        )
                        ->orderBy('name')
                        ->get(),

                'capabilities' =>
                    $this->capabilities(
                        $request
                    ),
            ]
        );
    }

    public function vouchers(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        $vouchers =
            HotspotVoucher::query()
                ->with([
                    'server:id,name',
                    'plan:id,name,price,validity_value,validity_unit,rate_limit',
                    'invoices' =>
                        function ($query) {
                            $query
                                ->orderByDesc(
                                    'id'
                                );
                        },
                ])
                ->latest('id')
                ->limit(150)
                ->get()
                ->map(
                    function (
                        HotspotVoucher $voucher
                    ): array {
                        $invoice =
                            $voucher
                                ->invoices
                                ->first();

                        return [
                            'id' =>
                                $voucher->id,

                            'username' =>
                                $voucher
                                    ->username,

                            'password' =>
                                $voucher
                                    ->password,

                            'status' =>
                                $voucher
                                    ->status,

                            'customer_name' =>
                                $voucher
                                    ->customer_name,

                            'phone' =>
                                $voucher
                                    ->phone,

                            'mac_address' =>
                                $voucher
                                    ->mac_address,

                            'sold_at' =>
                                $voucher
                                    ->sold_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'activated_at' =>
                                $voucher
                                    ->activated_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'expires_at' =>
                                $voucher
                                    ->expires_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'server' =>
                                $voucher
                                    ->server,

                            'plan' =>
                                $voucher
                                    ->plan,

                            'invoice' =>
                                $invoice
                                    ? [
                                        'id' =>
                                            $invoice
                                                ->id,

                                        'invoice_no' =>
                                            $invoice
                                                ->invoice_no,

                                        'amount' =>
                                            (float)
                                            $invoice
                                                ->amount,

                                        'paid_amount' =>
                                            (float)
                                            $invoice
                                                ->paid_amount,

                                        'due_amount' =>
                                            (float)
                                            $invoice
                                                ->due_amount,

                                        'status' =>
                                            $invoice
                                                ->status,
                                    ]
                                    : null,
                        ];
                    }
                );

        return Inertia::render(
            'Hotspot/Vouchers',
            [
                'vouchers' =>
                    $vouchers,

                'servers' =>
                    HotspotServer::query()
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),

                'plans' =>
                    HotspotPlan::query()
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderBy('name')
                        ->get(),

                'capabilities' =>
                    $this->capabilities(
                        $request
                    ),
            ]
        );
    }

    public function sessions(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        return Inertia::render(
            'Hotspot/Sessions',
            [
                'sessions' =>
                    HotspotSession::query()
                        ->where(
                            'active',
                            true
                        )
                        ->with([
                            'server:id,name',
                            'voucher:id,username',
                        ])
                        ->orderByDesc(
                            'last_seen_at'
                        )
                        ->limit(200)
                        ->get(),

                'capabilities' =>
                    $this->capabilities(
                        $request
                    ),
            ]
        );
    }

    public function billing(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        return Inertia::render(
            'Hotspot/Billing',
            [
                'dueInvoices' =>
                    HotspotInvoice::query()
                        ->with([
                            'voucher.plan:id,name',
                        ])
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->where(
                            'due_amount',
                            '>',
                            0
                        )
                        ->orderBy(
                            'due_date'
                        )
                        ->orderBy('id')
                        ->limit(200)
                        ->get(),

                'recentPayments' =>
                    HotspotPayment::query()
                        ->with([
                            'voucher:id,username',
                            'invoice:id,invoice_no',
                        ])
                        ->orderByDesc(
                            'payment_date'
                        )
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get(),

                'summary' => [
                    'due' =>
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

                    'received' =>
                        round(
                            (float)
                            HotspotPayment::query()
                                ->sum(
                                    'amount'
                                ),
                            2
                        ),
                ],

                'capabilities' =>
                    $this->capabilities(
                        $request
                    ),
            ]
        );
    }

    private function capabilities(
        Request $request
    ): array {
        $user =
            $request->user();

        return [
            'manage' =>
                $user->hasPermission(
                    'hotspot.manage'
                ),

            'sell' =>
                $user->hasPermission(
                    'hotspot.sell'
                ),

            'payments' =>
                $user->hasPermission(
                    'hotspot.payments'
                ),

            'export' =>
                $user->hasPermission(
                    'hotspot.export'
                ),
        ];
    }

    private function viewAccess(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->hasAnyPermission([
                    'hotspot.view',
                    'hotspot.manage',
                    'hotspot.sell',
                    'hotspot.payments',
                    'hotspot.export',
                ]),
            403
        );
    }
}
