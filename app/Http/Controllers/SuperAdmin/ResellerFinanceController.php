<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerAuditLog;
use App\Models\ResellerRecharge;
use App\Models\ResellerWalletTransaction;
use App\Services\Reseller\ResellerWalletService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResellerFinanceController extends Controller
{
    public function ledger(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        $query =
            ResellerWalletTransaction::query()
                ->with([
                    'reseller:id,code,company_name',
                    'creator:id,name',
                ]);

        if (
            $request->filled(
                'reseller_id'
            )
        ) {
            $query->where(
                'reseller_id',
                $request->integer(
                    'reseller_id'
                )
            );
        }

        if (
            $request->filled(
                'direction'
            )
        ) {
            $query->where(
                'direction',
                $request->string(
                    'direction'
                )->toString()
            );
        }

        return Inertia::render(
            'SuperAdmin/Wallet/Index',
            [
                'transactions' =>
                    $query
                        ->latest('id')
                        ->limit(300)
                        ->get(),

                'resellers' =>
                    Reseller::query()
                        ->orderBy(
                            'company_name'
                        )
                        ->get([
                            'id',
                            'code',
                            'company_name',
                            'wallet_balance',
                        ]),

                'filters' => [
                    'reseller_id' =>
                        $request->input(
                            'reseller_id'
                        ),

                    'direction' =>
                        $request->input(
                            'direction'
                        ),
                ],

                'summary' =>
                    $this->summary(),
            ]
        );
    }

    public function recharges(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        return Inertia::render(
            'SuperAdmin/Recharges/Index',
            [
                'recharges' =>
                    ResellerRecharge::query()
                        ->with([
                            'reseller:id,code,company_name',
                            'approver:id,name',
                            'walletTransaction',
                        ])
                        ->latest('id')
                        ->limit(300)
                        ->get(),

                'summary' =>
                    $this->summary(),
            ]
        );
    }

    public function recharge(
        Request $request,
        Reseller $reseller,
        ResellerWalletService $wallet
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:999999999',
                ],

                'payment_method' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'reference' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        DB::transaction(
            function () use (
                $request,
                $reseller,
                $wallet,
                $data
            ): void {
                $recharge =
                    ResellerRecharge::create([
                        'recharge_no' =>
                            $this
                                ->rechargeNumber(),

                        'reseller_id' =>
                            $reseller->id,

                        'amount' =>
                            round(
                                (float)
                                $data['amount'],
                                2
                            ),

                        'payment_method' =>
                            $data[
                                'payment_method'
                            ],

                        'reference' =>
                            $data[
                                'reference'
                            ] ?? null,

                        'status' =>
                            'approved',

                        'requested_by' =>
                            $request
                                ->user()
                                ->id,

                        'approved_by' =>
                            $request
                                ->user()
                                ->id,

                        'approved_at' =>
                            Carbon::now(
                                'Asia/Qatar'
                            ),

                        'notes' =>
                            $data[
                                'notes'
                            ] ?? null,
                    ]);

                $transaction =
                    $wallet->credit(
                        $reseller,
                        (float)
                        $data['amount'],
                        'recharge',
                        $request
                            ->user()
                            ->id,
                        $data[
                            'payment_method'
                        ],
                        $recharge
                            ->recharge_no,
                        $data[
                            'notes'
                        ] ?? null
                    );

                $recharge->forceFill([
                    'wallet_transaction_id' =>
                        $transaction->id,
                ])->save();

                $this->audit(
                    $request,
                    $reseller,
                    'wallet.recharged',
                    [
                        'recharge_no' =>
                            $recharge
                                ->recharge_no,

                        'amount' =>
                            (float)
                            $data['amount'],

                        'wallet_transaction_id' =>
                            $transaction->id,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Reseller wallet recharged successfully.'
        );
    }

    public function deduct(
        Request $request,
        Reseller $reseller,
        ResellerWalletService $wallet
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:999999999',
                ],

                'reference' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'notes' => [
                    'required',
                    'string',
                    'max:2000',
                ],
            ]);

        $transaction =
            $wallet->debit(
                $reseller,
                (float)
                $data['amount'],
                'manual_deduction',
                $request->user()->id,
                null,
                $data[
                    'reference'
                ] ?? null,
                $data['notes']
            );

        $this->audit(
            $request,
            $reseller,
            'wallet.deducted',
            [
                'transaction_id' =>
                    $transaction->id,

                'amount' =>
                    (float)
                    $data['amount'],
            ]
        );

        return back()->with(
            'success',
            'Amount deducted from reseller wallet.'
        );
    }

    public function adjustment(
        Request $request,
        Reseller $reseller,
        ResellerWalletService $wallet
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'direction' => [
                    'required',
                    Rule::in([
                        'credit',
                        'debit',
                    ]),
                ],

                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:999999999',
                ],

                'reference' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'notes' => [
                    'required',
                    'string',
                    'max:2000',
                ],
            ]);

        if (
            $data['direction']
            === 'credit'
        ) {
            $transaction =
                $wallet->credit(
                    $reseller,
                    (float)
                    $data['amount'],
                    'manual_adjustment',
                    $request->user()->id,
                    null,
                    $data[
                        'reference'
                    ] ?? null,
                    $data['notes']
                );
        } else {
            $transaction =
                $wallet->debit(
                    $reseller,
                    (float)
                    $data['amount'],
                    'manual_adjustment',
                    $request->user()->id,
                    null,
                    $data[
                        'reference'
                    ] ?? null,
                    $data['notes']
                );
        }

        $this->audit(
            $request,
            $reseller,
            'wallet.adjusted',
            [
                'transaction_id' =>
                    $transaction->id,

                'direction' =>
                    $data[
                        'direction'
                    ],

                'amount' =>
                    (float)
                    $data['amount'],
            ]
        );

        return back()->with(
            'success',
            'Wallet adjustment completed.'
        );
    }

    private function summary(): array
    {
        $today =
            Carbon::now(
                'Asia/Qatar'
            );

        return [
            'total_wallet_balance' =>
                round(
                    (float)
                    Reseller::query()
                        ->sum(
                            'wallet_balance'
                        ),
                    2
                ),

            'total_credits' =>
                round(
                    (float)
                    ResellerWalletTransaction::query()
                        ->where(
                            'direction',
                            'credit'
                        )
                        ->sum(
                            'amount'
                        ),
                    2
                ),

            'total_debits' =>
                round(
                    (float)
                    ResellerWalletTransaction::query()
                        ->where(
                            'direction',
                            'debit'
                        )
                        ->sum(
                            'amount'
                        ),
                    2
                ),

            'today_recharge' =>
                round(
                    (float)
                    ResellerRecharge::query()
                        ->where(
                            'status',
                            'approved'
                        )
                        ->whereDate(
                            'approved_at',
                            $today
                                ->toDateString()
                        )
                        ->sum(
                            'amount'
                        ),
                    2
                ),

            'month_recharge' =>
                round(
                    (float)
                    ResellerRecharge::query()
                        ->where(
                            'status',
                            'approved'
                        )
                        ->whereBetween(
                            'approved_at',
                            [
                                $today
                                    ->copy()
                                    ->startOfMonth(),

                                $today
                                    ->copy()
                                    ->endOfMonth(),
                            ]
                        )
                        ->sum(
                            'amount'
                        ),
                    2
                ),
        ];
    }

    private function rechargeNumber(): string
    {
        do {
            $number =
                'RCH-'
                . now(
                    'Asia/Qatar'
                )->format(
                    'YmdHis'
                )
                . '-'
                . Str::upper(
                    Str::random(6)
                );
        } while (
            ResellerRecharge::query()
                ->where(
                    'recharge_no',
                    $number
                )
                ->exists()
        );

        return $number;
    }

    private function audit(
        Request $request,
        Reseller $reseller,
        string $action,
        array $metadata = []
    ): void {
        ResellerAuditLog::create([
            'reseller_id' =>
                $reseller->id,

            'user_id' =>
                $request
                    ->user()
                    ->id,

            'action' =>
                $action,

            'subject_type' =>
                Reseller::class,

            'subject_id' =>
                $reseller->id,

            'metadata' =>
                $metadata,

            'ip_address' =>
                $request->ip(),
        ]);
    }

    private function superAdmin(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user->isSuperAdmin(),
            403
        );
    }
}
