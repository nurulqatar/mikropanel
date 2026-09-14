<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientRefund;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientRefundService
{
    public function preview(
        Client $client
    ): array {
        $client->loadMissing(
            'package'
        );

        $invoices = Invoice::query()
            ->where(
                'client_id',
                $client->id
            )
            ->where(
                'applies_service_period',
                true
            )
            ->whereNull(
                'service_cancelled_at'
            )
            ->whereHas(
                'payments'
            )
            ->latest('id')
            ->get();

        foreach ($invoices as $invoice) {
            $preview =
                $this->calculate(
                    $client,
                    $invoice
                );

            if (
                $preview['eligible']
                && $preview['period_active']
            ) {
                return $preview;
            }
        }

        return $this->emptyPreview(
            'No refundable active service period was found.'
        );
    }

    public function refund(
        Client $client,
        int $invoiceId,
        string $reason
    ): array {
        return DB::transaction(
            function () use (
                $client,
                $invoiceId,
                $reason
            ): array {
                $lockedClient =
                    Client::query()
                        ->with(
                            'package'
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            $client->id
                        );

                $invoice =
                    Invoice::query()
                        ->where(
                            'client_id',
                            $lockedClient->id
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            $invoiceId
                        );

                if (
                    $invoice
                        ->service_cancelled_at
                ) {
                    throw ValidationException::withMessages([
                        'refund' =>
                            'This service period has already been refunded.',
                    ]);
                }

                $preview =
                    $this->calculate(
                        $lockedClient,
                        $invoice
                    );

                if (
                    !$preview['eligible']
                    || !$preview[
                        'period_active'
                    ]
                ) {
                    throw ValidationException::withMessages([
                        'refund' =>
                            $preview['message']
                            ?: 'This service period is not refundable.',
                    ]);
                }

                $payments =
                    Payment::query()
                        ->where(
                            'invoice_id',
                            $invoice->id
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get();

                if (
                    $payments->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'refund' =>
                            'No received payment is available for refund.',
                    ]);
                }

                $refundAmount =
                    round(
                        (float)
                        $preview[
                            'refund_amount'
                        ],
                        2
                    );

                if (
                    $refundAmount < 0.01
                ) {
                    throw ValidationException::withMessages([
                        'refund' =>
                            'No refundable paid amount remains.',
                    ]);
                }

                $batchUuid =
                    (string)
                    Str::uuid();

                $remaining =
                    $refundAmount;

                $expiryBefore =
                    $lockedClient
                        ->expiry_date
                        ?->toDateString();

                foreach (
                    $payments
                    as $payment
                ) {
                    if (
                        $remaining <= 0
                    ) {
                        break;
                    }

                    $alreadyRefunded =
                        round(
                            (float)
                            ClientRefund::query()
                                ->where(
                                    'payment_id',
                                    $payment->id
                                )
                                ->sum(
                                    'amount'
                                ),
                            2
                        );

                    $available =
                        max(
                            0,
                            round(
                                (float)
                                $payment->amount
                                - $alreadyRefunded,
                                2
                            )
                        );

                    if (
                        $available <= 0
                    ) {
                        continue;
                    }

                    $allocation =
                        min(
                            $remaining,
                            $available
                        );

                    ClientRefund::create([
                        'reseller_id' =>
                            $lockedClient
                                ->reseller_id,

                        'batch_uuid' =>
                            $batchUuid,

                        'client_id' =>
                            $lockedClient->id,

                        'invoice_id' =>
                            $invoice->id,

                        'payment_id' =>
                            $payment->id,

                        'amount' =>
                            $allocation,

                        'refund_date' =>
                            Carbon::today(
                                'Asia/Qatar'
                            )->toDateString(),

                        'validity_days' =>
                            $preview[
                                'validity_days'
                            ],

                        'used_days' =>
                            $preview[
                                'used_days'
                            ],

                        'daily_rate' =>
                            $preview[
                                'daily_rate'
                            ],

                        'service_price' =>
                            $preview[
                                'service_price'
                            ],

                        'used_value' =>
                            $preview[
                                'used_value'
                            ],

                        'unused_value' =>
                            $preview[
                                'unused_value'
                            ],

                        'service_start_date' =>
                            $preview[
                                'service_start_date'
                            ],

                        'service_end_date' =>
                            $preview[
                                'service_end_date'
                            ],

                        'client_expiry_before' =>
                            $expiryBefore,

                        'reason' =>
                            $reason,

                        'refunded_by' =>
                            auth()->id(),
                    ]);

                    $remaining =
                        round(
                            $remaining
                            - $allocation,
                            2
                        );
                }

                if (
                    $remaining > 0.009
                ) {
                    throw ValidationException::withMessages([
                        'refund' =>
                            'Refund allocation failed safely. No partial refund was saved.',
                    ]);
                }

                $totalRefunded =
                    round(
                        (float)
                        ClientRefund::query()
                            ->where(
                                'invoice_id',
                                $invoice->id
                            )
                            ->sum(
                                'amount'
                            ),
                        2
                    );

                $grossPaid =
                    round(
                        (float)
                        Payment::query()
                            ->where(
                                'invoice_id',
                                $invoice->id
                            )
                            ->sum(
                                'amount'
                            ),
                        2
                    );

                $netRetained =
                    max(
                        0,
                        round(
                            $grossPaid
                            - $totalRefunded,
                            2
                        )
                    );

                /*
                 * Refunded unused service must NOT
                 * become customer due again.
                 */
                $invoice->forceFill([
                    'paid_amount' =>
                        $netRetained,

                    'due_amount' =>
                        0,

                    'status' =>
                        'refunded',

                    'refunded_amount' =>
                        $totalRefunded,

                    'service_cancelled_at' =>
                        Carbon::now(
                            'Asia/Qatar'
                        ),
                ])->save();

                /*
                 * Panel state changes immediately.
                 */
                $lockedClient->forceFill([
                    'enabled' => false,
                    'connected' => false,
                    'expiry_date' =>
                        Carbon::today(
                            'Asia/Qatar'
                        )->toDateString(),
                ])->save();

                return [
                    ...$preview,

                    'batch_uuid' =>
                        $batchUuid,

                    'refund_amount' =>
                        $refundAmount,

                    'client_id' =>
                        $lockedClient->id,

                    'invoice_id' =>
                        $invoice->id,
                ];
            }
        );
    }

    private function calculate(
        Client $client,
        Invoice $invoice
    ): array {
        if (
            $invoice
                ->service_cancelled_at
        ) {
            return $this->emptyPreview(
                'This service period has already been refunded.'
            );
        }

        $validityDays =
            (int) (
                $invoice
                    ->service_validity_days
                ?: $client
                    ->package
                    ?->validity_days
                ?: 0
            );

        if (
            $validityDays < 1
        ) {
            return $this->emptyPreview(
                'Package validity is unavailable.'
            );
        }

        $servicePrice =
            round(
                (float) (
                    $invoice
                        ->service_price_snapshot
                    ?: (
                        (float)
                        $invoice->amount
                        - (float)
                        $invoice->discount
                    )
                ),
                2
            );

        if (
            $servicePrice <= 0
        ) {
            return $this->emptyPreview(
                'Service price is unavailable.'
            );
        }

        $startValue =
            $invoice
                ->service_start_date
            ?: $invoice
                ->issue_date;

        if (
            !$startValue
        ) {
            return $this->emptyPreview(
                'Service start date is unavailable.'
            );
        }

        $start =
            Carbon::parse(
                $startValue,
                'Asia/Qatar'
            )->startOfDay();

        $end =
            $invoice
                ->service_end_date
            ? Carbon::parse(
                $invoice
                    ->service_end_date,
                'Asia/Qatar'
            )->startOfDay()
            : $start
                ->copy()
                ->addDays(
                    $validityDays
                );

        $today =
            Carbon::today(
                'Asia/Qatar'
            );

        $periodActive =
            $today
                ->greaterThanOrEqualTo(
                    $start
                )
            && $today->lt(
                $end
            );

        if (
            !$periodActive
        ) {
            return [
                ...$this->emptyPreview(
                    'The paid service period is not currently active.'
                ),

                'invoice_id' =>
                    $invoice->id,

                'invoice_no' =>
                    $invoice->invoice_no,

                'period_active' =>
                    false,
            ];
        }

        $usedDays =
            min(
                $validityDays,
                max(
                    0,
                    (int)
                    $start->diffInDays(
                        $today
                    )
                )
            );

        $dailyRate =
            $servicePrice
            / $validityDays;

        $usedValue =
            min(
                $servicePrice,
                round(
                    $dailyRate
                    * $usedDays,
                    2
                )
            );

        $unusedValue =
            max(
                0,
                round(
                    $servicePrice
                    - $usedValue,
                    2
                )
            );

        $grossPaid =
            round(
                (float)
                Payment::query()
                    ->where(
                        'invoice_id',
                        $invoice->id
                    )
                    ->sum(
                        'amount'
                    ),
                2
            );

        $alreadyRefunded =
            round(
                (float)
                ClientRefund::query()
                    ->where(
                        'invoice_id',
                        $invoice->id
                    )
                    ->sum(
                        'amount'
                    ),
                2
            );

        $netPaid =
            max(
                0,
                round(
                    $grossPaid
                    - $alreadyRefunded,
                    2
                )
            );

        /*
         * Important partial-payment rule:
         *
         * QAR 30 package
         * QAR 20 actually paid
         * QAR 7 service already used
         * Refund = 20 - 7 = QAR 13
         */
        $paidValueAfterUsage =
            max(
                0,
                round(
                    $netPaid
                    - $usedValue,
                    2
                )
            );

        $refundAmount =
            min(
                $unusedValue,
                $paidValueAfterUsage
            );

        $eligible =
            $refundAmount >= 0.01;

        return [
            'eligible' =>
                $eligible,

            'period_active' =>
                true,

            'message' =>
                $eligible
                    ? null
                    : 'The paid amount does not exceed the service value already used.',

            'invoice_id' =>
                $invoice->id,

            'invoice_no' =>
                $invoice->invoice_no,

            'service_price' =>
                $servicePrice,

            'validity_days' =>
                $validityDays,

            'used_days' =>
                $usedDays,

            'daily_rate' =>
                round(
                    $dailyRate,
                    6
                ),

            'used_value' =>
                $usedValue,

            'unused_value' =>
                $unusedValue,

            'gross_paid' =>
                $grossPaid,

            'already_refunded' =>
                $alreadyRefunded,

            'net_paid' =>
                $netPaid,

            'refund_amount' =>
                round(
                    $refundAmount,
                    2
                ),

            'service_start_date' =>
                $start
                    ->toDateString(),

            'service_end_date' =>
                $end
                    ->toDateString(),
        ];
    }

    private function emptyPreview(
        string $message
    ): array {
        return [
            'eligible' => false,
            'period_active' => false,
            'message' => $message,
            'invoice_id' => null,
            'invoice_no' => null,
            'service_price' => 0,
            'validity_days' => 0,
            'used_days' => 0,
            'daily_rate' => 0,
            'used_value' => 0,
            'unused_value' => 0,
            'gross_paid' => 0,
            'already_refunded' => 0,
            'net_paid' => 0,
            'refund_amount' => 0,
            'service_start_date' => null,
            'service_end_date' => null,
        ];
    }
}
