<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionHotspotVoucher;
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

        return Inertia::render(
            'Hotspot/Batches',
            [
                'batches' =>
                    HotspotBatch::query()
                        ->with([
                            'server:id,name',
                            'plan:id,name,price',
                        ])
                        ->withCount(
                            'vouchers'
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->limit(100)
                        ->get(),
            ]
        );
    }

    public function renew(
        Request $request,
        int $voucher,
        HotspotBillingService $billing
    ): RedirectResponse {
        $this->admin($request);

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
        $this->admin($request);

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
        $this->admin($request);

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

        if (!$voucher->sold_at) {
            return back()->withErrors([
                'activate' =>
                    'Voucher must be sold before activation.',
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
        $this->admin($request);

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

        if ($voucher->sold_at) {
            ProvisionHotspotVoucher::dispatch(
                $voucher->id
            );
        }

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
        $this->admin($request);

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

        SuspendHotspotVoucher::dispatch(
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
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->isAdmin(),
            403
        );
    }
}
