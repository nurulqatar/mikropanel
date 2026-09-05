<?php

namespace App\Services\Hotspot;

use App\Jobs\ProvisionHotspotVoucher;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
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

                $price = round(
                    (float) $locked
                        ->plan
                        ->price,
                    2
                );

                if ($price <= 0) {
                    throw ValidationException::withMessages([
                        'sale_type' =>
                            'Plan price must be greater than zero.',
                    ]);
                }

                $saleType =
                    $data['sale_type'];

                $received = match (
                    $saleType
                ) {
                    'paid' => $price,

                    'partial' => round(
                        (float) (
                            $data[
                                'received_amount'
                            ] ?? 0
                        ),
                        2
                    ),

                    default => 0,
                };

                if (
                    $saleType === 'partial'
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
                        $price - $received,
                        2
                    )
                );

                $status =
                    $due <= 0
                        ? 'paid'
                        : (
                            $received > 0
                                ? 'partial'
                                : 'unpaid'
                        );

                $today = Carbon::now(
                    'Asia/Qatar'
                )->startOfDay();

                $defaultDueDays = max(
                    0,
                    (int) Setting::getValue(
                        'default_due_days',
                        0
                    )
                );

                $invoiceNo =
                    'HINV-'
                    . $today->format(
                        'Ymd'
                    )
                    . '-'
                    . $locked->id
                    . '-'
                    . Str::upper(
                        Str::random(4)
                    );

                $invoice =
                    HotspotInvoice::create([
                        'hotspot_voucher_id' =>
                            $locked->id,

                        'invoice_no' =>
                            $invoiceNo,

                        'amount' =>
                            $price,

                        'discount' =>
                            0,

                        'paid_amount' =>
                            $received,

                        'due_amount' =>
                            $due,

                        'issue_date' =>
                            $today
                                ->toDateString(),

                        'due_date' =>
                            $today
                                ->copy()
                                ->addDays(
                                    $defaultDueDays
                                )
                                ->toDateString(),

                        'status' =>
                            $status,

                        'notes' =>
                            'Hotspot voucher sale - '
                            . $locked
                                ->plan
                                ->name,

                        'created_by' =>
                            $userId,
                    ]);

                if ($received > 0) {
                    HotspotPayment::create([
                        'hotspot_invoice_id' =>
                            $invoice->id,

                        'hotspot_voucher_id' =>
                            $locked->id,

                        'amount' =>
                            $received,

                        'payment_date' =>
                            $today
                                ->toDateString(),

                        'payment_method' =>
                            $data[
                                'payment_method'
                            ],

                        'transaction_id' =>
                            $data[
                                'transaction_id'
                            ] ?? null,

                        'notes' =>
                            'Hotspot voucher sale payment',

                        'received_by' =>
                            $userId,
                    ]);
                }

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

        /*
         * Router write happens only after
         * billing transaction commits.
         */
        ProvisionHotspotVoucher::dispatch(
            $voucher->id
        );

        return $invoice;
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
                    (float) HotspotPayment::query()
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
                        (float) $locked->amount
                        - (float) $locked->discount,
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

                $paymentAmount = round(
                    (float) $data['amount'],
                    2
                );

                if (
                    $paymentAmount <= 0
                    || $paymentAmount
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
                        $paymentAmount,

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
                    + $paymentAmount,
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

                    'status' =>
                        $newDue <= 0
                            ? 'paid'
                            : 'partial',
                ])->save();

                return $locked->fresh();
            }
        );
    }
}
