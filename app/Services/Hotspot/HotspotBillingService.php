<?php

namespace App\Services\Hotspot;

use App\Jobs\ProvisionHotspotVoucher;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotPlan;
use App\Models\HotspotVoucher;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HotspotBillingService
{
    public function sellVoucher(
        HotspotVoucher $voucher,
        array $data,
        int $userId
    ): HotspotInvoice {
        $invoice = DB::transaction(
            function () use (
                $voucher,
                $data,
                $userId
            ): HotspotInvoice {
                $locked =
                    HotspotVoucher::query()
                        ->with('plan')
                        ->lockForUpdate()
                        ->findOrFail(
                            $voucher->id
                        );

                if (
                    $locked->invoices()
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'sale_type' =>
                            'This voucher has already been sold.',
                    ]);
                }

                if (
                    $locked->status
                    !== 'unused'
                ) {
                    throw ValidationException::withMessages([
                        'sale_type' =>
                            'Only unused vouchers can be sold.',
                    ]);
                }

                if (!$locked->plan) {
                    throw ValidationException::withMessages([
                        'sale_type' =>
                            'Voucher plan is missing.',
                    ]);
                }

                $money =
                    $this->resolvePayment(
                        (float) $locked
                            ->plan
                            ->price,
                        $data
                    );

                $today = Carbon::now(
                    'Asia/Qatar'
                )->startOfDay();

                $invoice =
                    HotspotInvoice::create([
                        'hotspot_voucher_id' =>
                            $locked->id,

                        'invoice_no' =>
                            $this
                                ->invoiceNumber(
                                    'SALE',
                                    $locked->id
                                ),

                        'invoice_type' =>
                            'sale',

                        'amount' =>
                            $money['price'],

                        'discount' =>
                            0,

                        'paid_amount' =>
                            $money['received'],

                        'due_amount' =>
                            $money['due'],

                        'issue_date' =>
                            $today
                                ->toDateString(),

                        'due_date' =>
                            $today
                                ->copy()
                                ->addDays(
                                    $this
                                        ->defaultDueDays()
                                )
                                ->toDateString(),

                        /*
                         * Initial voucher validity
                         * starts on first successful
                         * Hotspot login.
                         */
                        'service_from' =>
                            null,

                        'service_until' =>
                            null,

                        'status' =>
                            $money['status'],

                        'notes' =>
                            'Initial Hotspot voucher sale - '
                            . $locked
                                ->plan
                                ->name,

                        'created_by' =>
                            $userId,
                    ]);

                $this->createPayment(
                    $invoice,
                    $locked,
                    $money['received'],
                    $data,
                    $userId,
                    'Hotspot voucher sale payment'
                );

                $locked->forceFill([
                    'customer_name' =>
                        $data[
                            'customer_name'
                        ] ?? null,

                    'phone' =>
                        $data[
                            'phone'
                        ] ?? null,

                    'sold_at' =>
                        Carbon::now(
                            'Asia/Qatar'
                        ),
                ])->save();

                return $invoice;
            }
        );

        ProvisionHotspotVoucher::dispatch(
            $voucher->id
        );

        return $invoice;
    }

    public function renewVoucher(
        HotspotVoucher $voucher,
        array $data,
        int $userId
    ): HotspotInvoice {
        $result = DB::transaction(
            function () use (
                $voucher,
                $data,
                $userId
            ): array {
                $locked =
                    HotspotVoucher::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $voucher->id
                        );

                if (!$locked->sold_at) {
                    throw ValidationException::withMessages([
                        'renewal' =>
                            'Unsold voucher cannot be renewed.',
                    ]);
                }

                if (
                    $locked->status
                    === 'archived'
                ) {
                    throw ValidationException::withMessages([
                        'renewal' =>
                            'Archived voucher cannot be renewed.',
                    ]);
                }

                $plan =
                    HotspotPlan::query()
                        ->where(
                            'enabled',
                            true
                        )
                        ->findOrFail(
                            $data[
                                'hotspot_plan_id'
                            ]
                        );

                $money =
                    $this->resolvePayment(
                        (float) $plan->price,
                        $data
                    );

                $now = Carbon::now(
                    'Asia/Qatar'
                );

                /*
                 * Active subscription extends from
                 * current expiry. Expired subscription
                 * starts a fresh period from now.
                 */
                $serviceFrom =
                    $locked->expires_at
                    && $locked
                        ->expires_at
                        ->isFuture()
                        ? $locked
                            ->expires_at
                            ->copy()
                        : $now->copy();

                $serviceUntil =
                    $serviceFrom
                        ->copy()
                        ->addSeconds(
                            $plan
                                ->validitySeconds()
                        );

                $invoice =
                    HotspotInvoice::create([
                        'hotspot_voucher_id' =>
                            $locked->id,

                        'invoice_no' =>
                            $this
                                ->invoiceNumber(
                                    'REN',
                                    $locked->id
                                ),

                        'invoice_type' =>
                            'renewal',

                        'amount' =>
                            $money['price'],

                        'discount' =>
                            0,

                        'paid_amount' =>
                            $money['received'],

                        'due_amount' =>
                            $money['due'],

                        'issue_date' =>
                            $now
                                ->toDateString(),

                        'due_date' =>
                            $now
                                ->copy()
                                ->addDays(
                                    $this
                                        ->defaultDueDays()
                                )
                                ->toDateString(),

                        'service_from' =>
                            $serviceFrom,

                        'service_until' =>
                            $serviceUntil,

                        'status' =>
                            $money['status'],

                        'notes' =>
                            'Hotspot renewal - '
                            . $plan->name,

                        'created_by' =>
                            $userId,
                    ]);

                $this->createPayment(
                    $invoice,
                    $locked,
                    $money['received'],
                    $data,
                    $userId,
                    'Hotspot renewal payment'
                );

                $locked->forceFill([
                    'hotspot_plan_id' =>
                        $plan->id,

                    'activated_at' =>
                        $locked
                            ->activated_at
                        ?? $now,

                    'expires_at' =>
                        $serviceUntil,

                    'status' =>
                        'active',
                ])->save();

                return [
                    'invoice' =>
                        $invoice,

                    'voucher_id' =>
                        $locked->id,
                ];
            }
        );

        ProvisionHotspotVoucher::dispatch(
            $result['voucher_id']
        );

        return $result['invoice'];
    }

    public function receivePayment(
        HotspotInvoice $invoice,
        array $data,
        int $userId
    ): HotspotInvoice {
        return DB::transaction(
            function () use (
                $invoice,
                $data,
                $userId
            ): HotspotInvoice {
                $locked =
                    HotspotInvoice::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $invoice->id
                        );

                if (
                    $locked->status
                    === 'cancelled'
                ) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Cancelled invoice cannot receive payment.',
                    ]);
                }

                $alreadyPaid = round(
                    (float)
                    HotspotPayment::query()
                        ->where(
                            'hotspot_invoice_id',
                            $locked->id
                        )
                        ->sum('amount'),
                    2
                );

                $netAmount = max(
                    0,
                    round(
                        (float)
                        $locked->amount
                        - (float)
                        $locked->discount,
                        2
                    )
                );

                $remainingDue = max(
                    0,
                    round(
                        $netAmount
                        - $alreadyPaid,
                        2
                    )
                );

                if ($remainingDue <= 0) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'This invoice is already fully paid.',
                    ]);
                }

                $amount = round(
                    (float)
                    $data['amount'],
                    2
                );

                if (
                    $amount <= 0
                    || $amount
                        > $remainingDue
                ) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Payment must be between QAR 0.01 and QAR '
                            . number_format(
                                $remainingDue,
                                2
                            )
                            . '.',
                    ]);
                }

                HotspotPayment::create([
                    'hotspot_invoice_id' =>
                        $locked->id,

                    'hotspot_voucher_id' =>
                        $locked
                            ->hotspot_voucher_id,

                    'amount' =>
                        $amount,

                    'payment_date' =>
                        Carbon::now(
                            'Asia/Qatar'
                        )->toDateString(),

                    'payment_method' =>
                        $data[
                            'payment_method'
                        ],

                    'transaction_id' =>
                        $data[
                            'transaction_id'
                        ] ?? null,

                    'notes' =>
                        'Hotspot due payment',

                    'received_by' =>
                        $userId,
                ]);

                $newPaid = round(
                    $alreadyPaid
                    + $amount,
                    2
                );

                $newDue = max(
                    0,
                    round(
                        $netAmount
                        - $newPaid,
                        2
                    )
                );

                $locked->forceFill([
                    'paid_amount' =>
                        $newPaid,

                    'due_amount' =>
                        $newDue,

                    /*
                     * Paying old due never extends
                     * voucher expiry a second time.
                     */
                    'status' =>
                        $newDue <= 0
                            ? 'paid'
                            : 'partial',
                ])->save();

                return $locked->fresh();
            }
        );
    }

    private function resolvePayment(
        float $price,
        array $data
    ): array {
        $price = round(
            $price,
            2
        );

        if ($price <= 0) {
            throw ValidationException::withMessages([
                'sale_type' =>
                    'Plan price must be greater than zero.',
            ]);
        }

        $type =
            $data['sale_type'];

        $received = match (
            $type
        ) {
            'paid' =>
                $price,

            'partial' =>
                round(
                    (float) (
                        $data[
                            'received_amount'
                        ] ?? 0
                    ),
                    2
                ),

            default =>
                0,
        };

        if (
            $type === 'partial'
            && (
                $received <= 0
                || $received >= $price
            )
        ) {
            throw ValidationException::withMessages([
                'received_amount' =>
                    'Partial payment must be greater than zero and less than QAR '
                    . number_format(
                        $price,
                        2
                    )
                    . '.',
            ]);
        }

        $due = max(
            0,
            round(
                $price
                - $received,
                2
            )
        );

        return [
            'price' =>
                $price,

            'received' =>
                $received,

            'due' =>
                $due,

            'status' =>
                $due <= 0
                    ? 'paid'
                    : (
                        $received > 0
                            ? 'partial'
                            : 'unpaid'
                    ),
        ];
    }

    private function createPayment(
        HotspotInvoice $invoice,
        HotspotVoucher $voucher,
        float $amount,
        array $data,
        int $userId,
        string $notes
    ): void {
        if ($amount <= 0) {
            return;
        }

        HotspotPayment::create([
            'hotspot_invoice_id' =>
                $invoice->id,

            'hotspot_voucher_id' =>
                $voucher->id,

            'amount' =>
                $amount,

            'payment_date' =>
                Carbon::now(
                    'Asia/Qatar'
                )->toDateString(),

            'payment_method' =>
                $data[
                    'payment_method'
                ],

            'transaction_id' =>
                $data[
                    'transaction_id'
                ] ?? null,

            'notes' =>
                $notes,

            'received_by' =>
                $userId,
        ]);
    }

    private function defaultDueDays(): int
    {
        return max(
            0,
            (int)
            Setting::getValue(
                'default_due_days',
                0
            )
        );
    }

    private function invoiceNumber(
        string $type,
        int $voucherId
    ): string {
        return 'HINV-'
            . $type
            . '-'
            . Carbon::now(
                'Asia/Qatar'
            )->format(
                'YmdHis'
            )
            . '-'
            . $voucherId
            . '-'
            . Str::upper(
                Str::random(4)
            );
    }
}
