<?php

namespace App\Http\Controllers;

use App\Jobs\DiscoverHotspotServersJob;
use App\Jobs\DisconnectHotspotSession;
use App\Jobs\SyncHotspotServer;
use App\Models\HotspotBatch;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotPlan;
use App\Models\HotspotServer;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotBillingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotspotController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->assertAdmin($request);

        $today = Carbon::now(
            'Asia/Qatar'
        )->startOfDay();

        $monthStart =
            $today
                ->copy()
                ->startOfMonth();

        $monthEnd =
            $today
                ->copy()
                ->endOfMonth();

        $servers =
            HotspotServer::query()
                ->with(
                    'router:id,name'
                )
                ->orderBy('name')
                ->get();

        $plans =
            HotspotPlan::query()
                ->orderByDesc('enabled')
                ->orderBy('name')
                ->get();

        $vouchers =
            HotspotVoucher::query()
                ->with([
                    'server:id,name',
                    'plan:id,name,price,validity_value,validity_unit,rate_limit',
                    'invoices' =>
                        function ($query) {
                            $query
                                ->orderByDesc('id');
                        },
                ])
                ->latest('id')
                ->limit(100)
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

                            /*
                             * Admin-only operations page.
                             * Password cast decrypts value.
                             */
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

                            'mikrotik_user_id' =>
                                $voucher
                                    ->mikrotik_user_id,

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
                                $voucher->server
                                    ? [
                                        'id' =>
                                            $voucher
                                                ->server
                                                ->id,

                                        'name' =>
                                            $voucher
                                                ->server
                                                ->name,
                                    ]
                                    : null,

                            'plan' =>
                                $voucher->plan
                                    ? [
                                        'id' =>
                                            $voucher
                                                ->plan
                                                ->id,

                                        'name' =>
                                            $voucher
                                                ->plan
                                                ->name,

                                        'price' =>
                                            (float)
                                            $voucher
                                                ->plan
                                                ->price,

                                        'validity_value' =>
                                            $voucher
                                                ->plan
                                                ->validity_value,

                                        'validity_unit' =>
                                            $voucher
                                                ->plan
                                                ->validity_unit,

                                        'rate_limit' =>
                                            $voucher
                                                ->plan
                                                ->rate_limit,
                                    ]
                                    : null,

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

        $sessions =
            HotspotSession::query()
                ->where('active', true)
                ->with([
                    'server:id,name',
                    'voucher:id,username',
                ])
                ->orderByDesc(
                    'last_seen_at'
                )
                ->limit(100)
                ->get();

        $dueInvoices =
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
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(100)
                ->get();

        $recentPayments =
            HotspotPayment::query()
                ->with([
                    'voucher:id,username',
                    'invoice:id,invoice_no',
                ])
                ->orderByDesc(
                    'payment_date'
                )
                ->orderByDesc('id')
                ->limit(20)
                ->get();

        return Inertia::render(
            'Hotspot/Index',
            [
                'servers' =>
                    $servers,

                'plans' =>
                    $plans,

                'vouchers' =>
                    $vouchers,

                'sessions' =>
                    $sessions,

                'dueInvoices' =>
                    $dueInvoices,

                'recentPayments' =>
                    $recentPayments,

                'stats' => [
                    'servers' =>
                        HotspotServer::query()
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

                    'sold_vouchers' =>
                        HotspotVoucher::query()
                            ->whereNotNull(
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

                    'active_sessions' =>
                        HotspotSession::query()
                            ->where(
                                'active',
                                true
                            )
                            ->count(),

                    'total_due' =>
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

                    'today_collection' =>
                        (float)
                        HotspotPayment::query()
                            ->whereDate(
                                'payment_date',
                                $today
                                    ->toDateString()
                            )
                            ->sum('amount'),

                    'month_collection' =>
                        (float)
                        HotspotPayment::query()
                            ->whereBetween(
                                'payment_date',
                                [
                                    $monthStart
                                        ->toDateString(),

                                    $monthEnd
                                        ->toDateString(),
                                ]
                            )
                            ->sum('amount'),
                ],

                'flash' => [
                    'success' =>
                        session(
                            'success'
                        ),

                    'error' =>
                        session(
                            'error'
                        ),
                ],
            ]
        );
    }

    public function discover(
        Request $request
    ): RedirectResponse {
        $this->assertAdmin($request);

        DiscoverHotspotServersJob::dispatch();

        return back()->with(
            'success',
            'Hotspot discovery queued. Refresh shortly to see discovered servers.'
        );
    }

    public function syncServer(
        Request $request,
        HotspotServer $server
    ): RedirectResponse {
        $this->assertAdmin($request);

        SyncHotspotServer::dispatch(
            $server->id
        );

        return back()->with(
            'success',
            'Hotspot server synchronization queued.'
        );
    }

    public function storePlan(
        Request $request
    ): RedirectResponse {
        $this->assertAdmin($request);

        $data =
            $this->validatePlan(
                $request
            );

        HotspotPlan::create($data);

        return back()->with(
            'success',
            'Hotspot plan created successfully.'
        );
    }

    public function updatePlan(
        Request $request,
        HotspotPlan $plan
    ): RedirectResponse {
        $this->assertAdmin($request);

        $data =
            $this->validatePlan(
                $request
            );

        $plan->update($data);

        return back()->with(
            'success',
            'Hotspot plan updated successfully.'
        );
    }

    public function destroyPlan(
        Request $request,
        HotspotPlan $plan
    ): RedirectResponse {
        $this->assertAdmin($request);

        if (
            $plan->vouchers()
                ->withTrashed()
                ->exists()
        ) {
            return back()->withErrors([
                'plan' =>
                    'This plan has voucher history and cannot be deleted. Disable it instead.',
            ]);
        }

        $plan->delete();

        return back()->with(
            'success',
            'Hotspot plan deleted successfully.'
        );
    }

    public function generateVouchers(
        Request $request
    ): RedirectResponse {
        $this->assertAdmin($request);

        $data = $request->validate([
            'hotspot_server_id' => [
                'required',
                Rule::exists(
                    'hotspot_servers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'enabled',
                            true
                        )
                ),
            ],

            'hotspot_plan_id' => [
                'required',
                Rule::exists(
                    'hotspot_plans',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'enabled',
                            true
                        )
                ),
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:500',
            ],

            'prefix' => [
                'nullable',
                'alpha_dash',
                'max:8',
            ],
        ]);

        $quantity =
            (int) $data['quantity'];

        $prefix =
            Str::upper(
                trim(
                    (string) (
                        $data['prefix']
                        ?? ''
                    )
                )
            );

        DB::transaction(
            function () use (
                $data,
                $quantity,
                $prefix,
                $request
            ): void {
                $batch =
                    HotspotBatch::create([
                        'batch_code' =>
                            'HB-'
                            . now(
                                'Asia/Qatar'
                            )->format(
                                'YmdHis'
                            )
                            . '-'
                            . Str::upper(
                                Str::random(5)
                            ),

                        'hotspot_server_id' =>
                            $data[
                                'hotspot_server_id'
                            ],

                        'hotspot_plan_id' =>
                            $data[
                                'hotspot_plan_id'
                            ],

                        'quantity' =>
                            $quantity,

                        'prefix' =>
                            $prefix !== ''
                                ? $prefix
                                : null,

                        'status' =>
                            'ready',

                        'created_by' =>
                            $request
                                ->user()
                                ->id,
                    ]);

                $generated = [];

                for (
                    $i = 0;
                    $i < $quantity;
                    $i++
                ) {
                    do {
                        $digits =
                            (string)
                            random_int(
                                100000,
                                999999
                            );

                        $username =
                            $prefix
                            . $digits;

                    } while (
                        isset(
                            $generated[
                                $username
                            ]
                        )
                        || HotspotVoucher::withTrashed()
                            ->where(
                                'username',
                                $username
                            )
                            ->exists()
                    );

                    $generated[
                        $username
                    ] = true;

                    $password =
                        (string)
                        random_int(
                            100000,
                            999999
                        );

                    HotspotVoucher::create([
                        'hotspot_batch_id' =>
                            $batch->id,

                        'hotspot_server_id' =>
                            $data[
                                'hotspot_server_id'
                            ],

                        'hotspot_plan_id' =>
                            $data[
                                'hotspot_plan_id'
                            ],

                        'username' =>
                            $username,

                        'password' =>
                            $password,

                        'status' =>
                            'unused',

                        'created_by' =>
                            $request
                                ->user()
                                ->id,
                    ]);
                }
            }
        );

        return back()->with(
            'success',
            "{$quantity} voucher(s) generated successfully. Sell a voucher to activate RouterOS provisioning."
        );
    }

    public function sellVoucher(
        Request $request,
        HotspotVoucher $voucher,
        HotspotBillingService $billing
    ): RedirectResponse {
        $this->assertAdmin($request);

        $data = $request->validate([
            'customer_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sale_type' => [
                'required',
                Rule::in([
                    'paid',
                    'due',
                    'partial',
                ]),
            ],

            'received_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'payment_method' => [
                'nullable',
                'required_unless:sale_type,due',
                'string',
                'max:100',
            ],

            'transaction_id' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $invoice =
            $billing->sellVoucher(
                $voucher,
                $data,
                $request->user()->id
            );

        return back()->with(
            'success',
            'Voucher sold successfully. Invoice '
            . $invoice->invoice_no
            . ' created and RouterOS provisioning queued.'
        );
    }

    public function receiveInvoicePayment(
        Request $request,
        HotspotInvoice $invoice,
        HotspotBillingService $billing
    ): RedirectResponse {
        $this->assertAdmin($request);

        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'payment_method' => [
                'required',
                'string',
                'max:100',
            ],

            'transaction_id' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $updated =
            $billing->receivePayment(
                $invoice,
                $data,
                $request->user()->id
            );

        return back()->with(
            'success',
            $updated->due_amount > 0
                ? 'Hotspot payment received. Remaining due QAR '
                    . number_format(
                        (float)
                        $updated->due_amount,
                        2
                    )
                    . '.'
                : 'Hotspot invoice fully paid.'
        );
    }

    public function disconnectSession(
        Request $request,
        HotspotSession $session
    ): RedirectResponse {
        $this->assertAdmin($request);

        DisconnectHotspotSession::dispatch(
            $session->id
        );

        return back()->with(
            'success',
            'Hotspot session disconnect queued.'
        );
    }

    private function validatePlan(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'validity_value' => [
                'required',
                'integer',
                'min:1',
                'max:3650',
            ],

            'validity_unit' => [
                'required',
                Rule::in([
                    'minutes',
                    'hours',
                    'days',
                ]),
            ],

            'rate_limit' => [
                'nullable',
                'string',
                'max:100',
            ],

            'shared_users' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'idle_timeout_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:1440',
            ],

            'keepalive_timeout_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:1440',
            ],

            'mac_binding' => [
                'required',
                'boolean',
            ],

            'enabled' => [
                'required',
                'boolean',
            ],
        ]);
    }

    private function assertAdmin(
        Request $request
    ): void {
        abort_unless(
            $request->user()
                && $request
                    ->user()
                    ->isAdmin(),
            403,
            'Hotspot management is currently restricted to administrators.'
        );
    }
}
