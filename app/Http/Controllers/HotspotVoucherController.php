<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionHotspotVoucher;
use App\Jobs\DeleteHotspotVoucherFromRouter;
use App\Jobs\SuspendHotspotVoucher;
use App\Models\HotspotBatch;
use App\Models\HotspotInvoice;
use App\Models\HotspotPlan;
use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotspotVoucherController extends Controller
{
    public function show(
        Request $request,
        int $voucher
    ): Response {
        $this->admin($request);

        $voucher =
            HotspotVoucher::withTrashed()
                ->with([
                    'server.router:id,name',
                    'plan',
                    'batch',
                    'invoices' =>
                        function ($query) {
                            $query
                                ->with('payments')
                                ->orderByDesc(
                                    'id'
                                );
                        },
                    'sessions' =>
                        function ($query) {
                            $query
                                ->orderByDesc(
                                    'id'
                                )
                                ->limit(30);
                        },
                ])
                ->findOrFail(
                    $voucher
                );

        return Inertia::render(
            'Hotspot/Voucher',
            [
                'voucher' =>
                    $voucher,

                'plans' =>
                    HotspotPlan::query()
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderBy('name')
                        ->get(),

                'flash' => [
                    'success' =>
                        session(
                            'success'
                        ),
                ],
            ]
        );
    }

    public function batches(
        Request $request
    ): Response {
        $this->admin($request);

        $batches = HotspotBatch::query()
            ->with([
                'zone:id,name,code',
                'server:id,name',
                'plan:id,name,price',
            ])
            ->withCount([
                'vouchers',
                'vouchers as sold_vouchers_count' =>
                    fn ($query) =>
                        $query->whereNotNull(
                            'sold_at'
                        ),
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(
                function (HotspotBatch $batch): array {
                    $routerQuery = DB::table('routers')
                        ->where(
                            'zone_id',
                            $batch->zone_id
                        )
                        ->where(
                            'enabled',
                            true
                        );

                    if ($batch->reseller_id) {
                        $routerQuery->where(
                            'reseller_id',
                            $batch->reseller_id
                        );
                    }

                    $routerIds = $routerQuery
                        ->orderBy('id')
                        ->pluck('id');

                    $routerCount =
                        $routerIds->count();

                    $voucherCount = (int)
                        $batch->vouchers_count;

                    $fullySyncedRouters = 0;
                    $partialRouters = 0;
                    $failedSyncs = 0;

                    foreach ($routerIds as $routerId) {
                        $base = DB::table(
                            'hotspot_voucher_router_syncs as s'
                        )
                            ->join(
                                'hotspot_vouchers as v',
                                'v.id',
                                '=',
                                's.hotspot_voucher_id'
                            )
                            ->where(
                                'v.hotspot_batch_id',
                                $batch->id
                            )
                            ->where(
                                's.router_id',
                                $routerId
                            )
                            ->whereNull(
                                'v.deleted_at'
                            );

                        $syncedForRouter =
                            (clone $base)
                                ->where(
                                    function ($healthy): void {
                                        $healthy
                                            ->where(
                                                function ($active): void {
                                                    $active
                                                        ->whereIn(
                                                            'v.status',
                                                            [
                                                                'unused',
                                                                'active',
                                                            ]
                                                        )
                                                        ->where(
                                                            's.status',
                                                            'synced'
                                                        );
                                                }
                                            )
                                            ->orWhere(
                                                function ($inactive): void {
                                                    $inactive
                                                        ->whereIn(
                                                            'v.status',
                                                            [
                                                                'suspended',
                                                                'expired',
                                                            ]
                                                        )
                                                        ->where(
                                                            's.status',
                                                            'suspended'
                                                        );
                                                }
                                            );
                                    }
                                )
                                ->count();

                        $failedForRouter =
                            (clone $base)
                                ->where(
                                    's.status',
                                    'failed'
                                )
                                ->count();

                        $seenForRouter =
                            (clone $base)->count();

                        $failedSyncs +=
                            $failedForRouter;

                        if (
                            $voucherCount > 0
                            && $syncedForRouter
                                >= $voucherCount
                        ) {
                            $fullySyncedRouters++;
                        } elseif ($seenForRouter > 0) {
                            $partialRouters++;
                        }
                    }

                    $syncStatus = match (true) {
                        $voucherCount === 0 =>
                            'EMPTY',
                        $routerCount === 0 =>
                            'NO ROUTER',
                        $fullySyncedRouters
                            === $routerCount =>
                            'SYNCED',
                        $fullySyncedRouters > 0
                            || $partialRouters > 0 =>
                            'PARTIAL',
                        default =>
                            'PENDING',
                    };

                    return [
                        'id' => $batch->id,
                        'batch_code' =>
                            $batch->batch_code,
                        'batch_name' =>
                            $batch->batch_name
                            ?: $batch->batch_code,
                        'zone' =>
                            $batch->zone,
                        'server' =>
                            $batch->server,
                        'plan' =>
                            $batch->plan,
                        'quantity' =>
                            (int) $batch->quantity,
                        'vouchers_count' =>
                            $voucherCount,
                        'sold_vouchers_count' =>
                            (int)
                            $batch->sold_vouchers_count,
                        'created_at' =>
                            $batch->created_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),
                        'sync_status' =>
                            $syncStatus,
                        'sync_synced' =>
                            $fullySyncedRouters,
                        'sync_expected' =>
                            $routerCount,
                        'sync_failed' =>
                            $failedSyncs,
                        'router_count' =>
                            $routerCount,
                    ];
                }
            );

        return Inertia::render(
            'Hotspot/Batches',
            [
                'batches' => $batches,
            ]
        );
    }

    public function renew(
        Request $request,
        int $voucher,
        HotspotBillingService $billing
    ): RedirectResponse {
        $this->manageAccess($request);

        $voucher =
            HotspotVoucher::query()
                ->findOrFail(
                    $voucher
                );

        $data = $request->validate([
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
            $billing->renewVoucher(
                $voucher,
                $data,
                $request->user()->id
            );

        return back()->with(
            'success',
            'Voucher renewed. Invoice '
            . $invoice->invoice_no
            . ' created.'
        );
    }

    public function suspend(
        Request $request,
        int $voucher
    ): RedirectResponse {
        $this->manageAccess($request);

        $voucher =
            HotspotVoucher::query()
                ->findOrFail(
                    $voucher
                );

        DB::transaction(
            function () use (
                $voucher
            ): void {
                $locked =
                    HotspotVoucher::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $voucher->id
                        );

                $locked->forceFill([
                    'status' =>
                        'suspended',
                ])->save();
            }
        );

        SuspendHotspotVoucher::dispatch(
            $voucher->id
        );

        return back()->with(
            'success',
            'Voucher suspended and active session disconnect queued.'
        );
    }

    public function activate(
        Request $request,
        int $voucher
    ): RedirectResponse {
        $this->manageAccess($request);

        $voucher =
            HotspotVoucher::query()
                ->findOrFail(
                    $voucher
                );

        if (
            $voucher->expires_at
            && $voucher
                ->expires_at
                ->isPast()
        ) {
            return back()->withErrors([
                'activate' =>
                    'Voucher is expired. Renew it first.',
            ]);
        }

        $voucher->forceFill([
            'status' =>
                $voucher
                    ->activated_at
                    ? 'active'
                    : 'unused',
        ])->save();

        ProvisionHotspotVoucher::dispatch(
            $voucher->id
        );

        return back()->with(
            'success',
            'Voucher activation queued.'
        );
    }

    public function updateMac(
        Request $request,
        int $voucher
    ): RedirectResponse {
        $this->manageAccess($request);

        $data = $request->validate([
            'mac_address' => [
                'nullable',
                'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/',
            ],
        ]);

        $voucher =
            HotspotVoucher::query()
                ->findOrFail(
                    $voucher
                );

        $mac = $data[
            'mac_address'
        ] ?? null;

        $voucher->forceFill([
            'mac_address' =>
                $mac
                    ? strtoupper(
                        $mac
                    )
                    : null,
        ])->save();

        ProvisionHotspotVoucher::dispatch(
            $voucher->id
        );

        return back()->with(
            'success',
            $mac
                ? 'Voucher MAC updated.'
                : 'Voucher MAC binding released.'
        );
    }

    public function archive(
        Request $request,
        int $voucher
    ): RedirectResponse {
        $this->manageAccess($request);

        $voucher =
            HotspotVoucher::query()
                ->findOrFail(
                    $voucher
                );

        DB::transaction(
            function () use (
                $voucher
            ): void {
                $locked =
                    HotspotVoucher::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $voucher->id
                        );

                $locked->forceFill([
                    'status' =>
                        'archived',
                ])->save();

                $locked->delete();
            }
        );

        DeleteHotspotVoucherFromRouter::dispatch(
            $voucher->id
        );

        return redirect()
            ->route(
                'hotspot.index'
            )
            ->with(
                'success',
                'Voucher archived. Billing history has been preserved.'
            );
    }

    private function admin(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isResellerOwner()
                || $user->hasAnyPermission([
                    'hotspot.view',
                    'hotspot.manage',
                    'hotspot.sell',
                    'hotspot.payments',
                    'hotspot.export',
                ])
            ),
            403
        );
    }

    private function manageAccess(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && !$user->isManager()
            && (
                $user->isResellerOwner()
                || $user->hasPermission(
                    'hotspot.manage'
                )
            ),
            403
        );
    }
}
