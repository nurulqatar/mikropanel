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
        /*
         * ACCOUNT_WIDE_PAID_REFUND_V2
         *
         * If any device in the account has due:
         * - include every eligible paid device refund;
         * - subtract unpaid service already used by
         *   due devices;
         * - produce one final account refund.
         *
         * Without due, old selected-device behaviour
         * remains unchanged.
         */
        $accountPreview =
            $this->buildAccountDueFamilyPreview(
                $client,
                false
            );

        if (
            $accountPreview[
                'account_due_mode'
            ]
            ?? false
        ) {
            return $accountPreview;
        }

        $client->loadMissing(
            'package'
        );

        $invoices =
            Invoice::query()
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

        foreach (
            $invoices
            as $invoice
        ) {
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
                /*
                 * ACCOUNT_DUE_REFUND_OFFSET_V1
                 *
                 * A due-mode refund becomes an account-level
                 * service termination. Lock the complete device
                 * family before calculating money.
                 */
                $familyClients =
                    $this->familyClients(
                        $client,
                        true
                    );

                $lockedClient =
                    $familyClients
                        ->first(
                            fn (Client $row) =>
                                (int) $row->id
                                === (int) $client->id
                        );

                if (!$lockedClient) {
                    throw ValidationException::withMessages([
                        'refund' =>
                            'The selected device is not available in this customer account.',
                    ]);
                }

                $lockedClient
                    ->loadMissing(
                        'package'
                    );

                /*
                 * ACCOUNT_WIDE_PAID_REFUND_V2
                 *
                 * Due-mode refund is account-wide.
                 */
                $accountPreview =
                    $this->buildAccountDueFamilyPreview(
                        $lockedClient,
                        true,
                        $familyClients
                    );

                if (
                    $accountPreview[
                        'account_due_mode'
                    ]
                    ?? false
                ) {
                    if (
                        !$accountPreview['eligible']
                        || !$accountPreview[
                            'period_active'
                        ]
                    ) {
                        throw ValidationException::withMessages([
                            'refund' =>
                                $accountPreview[
                                    'message'
                                ]
                                ?: 'No account refund is available.',
                        ]);
                    }

                    if (
                        (int) (
                            $accountPreview[
                                'invoice_id'
                            ]
                            ?? 0
                        )
                        !==
                        (int) $invoiceId
                    ) {
                        throw ValidationException::withMessages([
                            'refund' =>
                                'Refund calculation changed. Reopen Refund Service and review the latest amount.',
                        ]);
                    }

                    return
                        $this->refundAccountDueFamily(
                            $familyClients,
                            $accountPreview,
                            $reason
                        );
                }

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

                $ledgerReason =
                    $reason;

                if (
                    $preview[
                        'account_due_mode'
                    ]
                    ?? false
                ) {
                    $ledgerReason .=
                        ' | [ACCOUNT_DUE_FAMILY_LOCK_V1] '
                        . 'Account due refund: outstanding QAR '
                        . number_format(
                            (float) (
                                $preview[
                                    'account_due_amount'
                                ]
                                ?? 0
                            ),
                            2,
                            '.',
                            ''
                        )
                        . '; unpaid used service retained QAR '
                        . number_format(
                            (float) (
                                $preview[
                                    'due_usage_offset'
                                ]
                                ?? 0
                            ),
                            2,
                            '.',
                            ''
                        )
                        . '; all devices suspended.';
                }

                $ledgerReason =
                    Str::limit(
                        $ledgerReason,
                        1000,
                        ''
                    );

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
                            $ledgerReason,

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

                $familyClientIds =
                    collect(
                        $preview[
                            'family_client_ids'
                        ]
                        ?? [
                            $lockedClient->id,
                        ]
                    )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->unique()
                        ->values();

                if (
                    $preview[
                        'account_due_mode'
                    ]
                    ?? false
                ) {
                    /*
                     * Only consumed unpaid service is deducted
                     * from the cash refund. The unused future
                     * part of every due service is cancelled.
                     */
                    $dueInvoiceIds =
                        collect(
                            $preview[
                                'due_adjustments'
                            ]
                            ?? []
                        )
                            ->pluck(
                                'invoice_id'
                            )
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            )
                            ->filter(
                                fn ($id) =>
                                    $id > 0
                                    && $id
                                        !==
                                        (int)
                                        $invoice->id
                            )
                            ->unique()
                            ->values();

                    if (
                        $dueInvoiceIds
                            ->isNotEmpty()
                    ) {
                        Invoice::withoutGlobalScopes()
                            ->whereIn(
                                'id',
                                $dueInvoiceIds
                            )
                            ->whereNull(
                                'service_cancelled_at'
                            )
                            ->update([
                                'due_amount' => 0,
                                'status' => 'cancelled',
                                'service_cancelled_at' =>
                                    Carbon::now(
                                        'Asia/Qatar'
                                    ),
                            ]);
                    }

                    Client::withoutGlobalScopes()
                        ->whereIn(
                            'id',
                            $familyClientIds
                        )
                        ->update([
                            'enabled' => false,
                            'connected' => false,
                            'expiry_date' =>
                                Carbon::today(
                                    'Asia/Qatar'
                                )->toDateString(),
                        ]);
                } else {
                    /*
                     * Existing normal refund behaviour.
                     */
                    $lockedClient->forceFill([
                        'enabled' => false,
                        'connected' => false,
                        'expiry_date' =>
                            Carbon::today(
                                'Asia/Qatar'
                            )->toDateString(),
                    ])->save();

                    $familyClientIds =
                        collect([
                            $lockedClient->id,
                        ]);
                }

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

                    'suspend_client_ids' =>
                        $familyClientIds
                            ->values()
                            ->all(),
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

    /*
     * ACCOUNT_WIDE_PAID_REFUND_V2
     *
     * Example:
     *
     * Paid Device A refund = QAR 25
     * Paid Device B refund = QAR 25
     * Due Device used unpaid = QAR 5
     *
     * Final refund = 25 + 25 - 5 = QAR 45
     */
    private function buildAccountDueFamilyPreview(
        Client $client,
        bool $lock,
        $familyClients = null
    ): array {
        $familyClients =
            $familyClients
            ?? $this->familyClients(
                $client,
                $lock
            );

        $familyClientIds =
            $familyClients
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->values();

        if (
            $familyClientIds->isEmpty()
        ) {
            return [
                'account_due_mode' => false,
            ];
        }

        $dueQuery =
            Invoice::withoutGlobalScopes()
                ->whereIn(
                    'client_id',
                    $familyClientIds
                )
                ->where(
                    'applies_service_period',
                    true
                )
                ->whereNull(
                    'service_cancelled_at'
                )
                ->whereNotIn(
                    'status',
                    [
                        'cancelled',
                        'refunded',
                    ]
                )
                ->where(
                    'due_amount',
                    '>',
                    0
                )
                ->orderBy('client_id')
                ->orderBy('id');

        if ($lock) {
            $dueQuery->lockForUpdate();
        }

        $dueInvoices =
            $dueQuery->get();

        $accountDue =
            round(
                (float)
                $dueInvoices->sum(
                    'due_amount'
                ),
                2
            );

        if ($accountDue <= 0) {
            return [
                'account_due_mode' => false,
            ];
        }

        $refundSources =
            $this->collectFamilyRefundSources(
                $familyClients,
                $lock
            );

        $baseRefund =
            round(
                (float)
                collect(
                    $refundSources
                )->sum(
                    'refund_amount'
                ),
                2
            );

        [
            $dueUsageOffset,
            $dueAdjustments,
            $calculationError,
        ] =
            $this->calculateDueUsageAdjustments(
                $familyClients,
                $dueInvoices
            );

        $finalRefund =
            max(
                0,
                round(
                    $baseRefund
                    - $dueUsageOffset,
                    2
                )
            );

        $primary =
            $refundSources[0]
            ?? null;

        $eligible =
            !$calculationError
            && count(
                $refundSources
            ) > 0
            && $finalRefund >= 0.01;

        if ($calculationError) {
            $message =
                $calculationError;
        } elseif (
            count(
                $refundSources
            ) === 0
        ) {
            $message =
                'This account has due, but no refundable paid device balance is available.';
        } elseif (
            $finalRefund < 0.01
        ) {
            $message =
                'No cash refund remains after deducting unpaid used service from due devices.';
        } else {
            $message = null;
        }

        return [
            'eligible' =>
                $eligible,

            'period_active' =>
                count(
                    $refundSources
                ) > 0,

            'message' =>
                $message,

            /*
             * Used as stale-preview token.
             */
            'invoice_id' =>
                $primary[
                    'invoice_id'
                ]
                ?? null,

            'invoice_no' =>
                count(
                    $refundSources
                ) > 1
                    ? 'MULTIPLE PAID DEVICES'
                    : (
                        $primary[
                            'invoice_no'
                        ]
                        ?? null
                    ),

            'service_price' =>
                round(
                    (float)
                    collect(
                        $refundSources
                    )->sum(
                        'service_price'
                    ),
                    2
                ),

            'validity_days' => 0,

            'used_days' =>
                (int)
                collect(
                    $refundSources
                )->sum(
                    'used_days'
                ),

            'daily_rate' => 0,

            'used_value' =>
                round(
                    (float)
                    collect(
                        $refundSources
                    )->sum(
                        'used_value'
                    ),
                    2
                ),

            'unused_value' =>
                round(
                    (float)
                    collect(
                        $refundSources
                    )->sum(
                        'unused_value'
                    ),
                    2
                ),

            'gross_paid' =>
                round(
                    (float)
                    collect(
                        $refundSources
                    )->sum(
                        'gross_paid'
                    ),
                    2
                ),

            'already_refunded' =>
                round(
                    (float)
                    collect(
                        $refundSources
                    )->sum(
                        'already_refunded'
                    ),
                    2
                ),

            'net_paid' =>
                round(
                    (float)
                    collect(
                        $refundSources
                    )->sum(
                        'net_paid'
                    ),
                    2
                ),

            'refund_amount' =>
                $finalRefund,

            'service_start_date' =>
                null,

            'service_end_date' =>
                null,

            'base_refund_amount' =>
                $baseRefund,

            'account_due_mode' =>
                true,

            'account_due_amount' =>
                $accountDue,

            'due_usage_offset' =>
                $dueUsageOffset,

            'due_adjustments' =>
                $dueAdjustments,

            'refund_sources' =>
                $refundSources,

            'family_device_count' =>
                $familyClientIds
                    ->count(),

            'family_client_ids' =>
                $familyClientIds
                    ->all(),

            'suspend_all_devices' =>
                true,
        ];
    }

    /*
     * Find latest refundable active period from every
     * paid device in this account.
     */
    private function collectFamilyRefundSources(
        $familyClients,
        bool $lock
    ): array {
        $sources = [];

        foreach (
            $familyClients
            as $owner
        ) {
            $owner->loadMissing(
                'package'
            );

            $query =
                Invoice::withoutGlobalScopes()
                    ->where(
                        'client_id',
                        $owner->id
                    )
                    ->where(
                        'applies_service_period',
                        true
                    )
                    ->whereNull(
                        'service_cancelled_at'
                    )
                    ->whereIn(
                        'id',
                        Payment::withoutGlobalScopes()
                            ->select(
                                'invoice_id'
                            )
                    )
                    ->orderByDesc('id');

            if ($lock) {
                $query->lockForUpdate();
            }

            foreach (
                $query->get()
                as $invoice
            ) {
                $calculated =
                    $this->calculate(
                        $owner,
                        $invoice
                    );

                if (
                    !$calculated[
                        'eligible'
                    ]
                    || !$calculated[
                        'period_active'
                    ]
                ) {
                    continue;
                }

                $sources[] = [
                    ...$calculated,

                    'client_id' =>
                        (int)
                        $owner->id,

                    'client_code' =>
                        $owner->client_code,

                    'device_label' =>
                        !$owner
                            ->parent_client_id
                            ? 'Main Device'
                            : (
                                $owner
                                    ->device_label
                                ?: 'Device #'
                                    . $owner->id
                            ),
                ];

                break;
            }
        }

        usort(
            $sources,
            fn (
                array $left,
                array $right
            ) =>
                $left['client_id']
                <=>
                $right['client_id']
        );

        return $sources;
    }

    private function calculateDueUsageAdjustments(
        $familyClients,
        $dueInvoices
    ): array {
        $today =
            Carbon::today(
                'Asia/Qatar'
            );

        $familyById =
            $familyClients
                ->keyBy('id');

        $offset = 0.0;
        $adjustments = [];

        foreach (
            $dueInvoices
            as $dueInvoice
        ) {
            $owner =
                $familyById->get(
                    $dueInvoice
                        ->client_id
                );

            if (!$owner) {
                return [
                    $offset,
                    $adjustments,
                    'A due device could not be resolved safely.',
                ];
            }

            $validityDays =
                (int) (
                    $dueInvoice
                        ->service_validity_days
                    ?: $owner
                        ->package
                        ?->validity_days
                    ?: 0
                );

            $servicePrice =
                round(
                    (float) (
                        $dueInvoice
                            ->service_price_snapshot
                        ?: (
                            (float)
                            $dueInvoice->amount
                            -
                            (float)
                            $dueInvoice->discount
                        )
                    ),
                    2
                );

            $startValue =
                $dueInvoice
                    ->service_start_date
                ?: $dueInvoice
                    ->issue_date;

            if (
                $validityDays < 1
                || $servicePrice <= 0
                || !$startValue
            ) {
                return [
                    $offset,
                    $adjustments,
                    'Unable to calculate used service safely for due invoice '
                    . $dueInvoice->invoice_no
                    . '.',
                ];
            }

            $start =
                Carbon::parse(
                    $startValue,
                    'Asia/Qatar'
                )->startOfDay();

            $end =
                $dueInvoice
                    ->service_end_date
                ? Carbon::parse(
                    $dueInvoice
                        ->service_end_date,
                    'Asia/Qatar'
                )->startOfDay()
                : $start
                    ->copy()
                    ->addDays(
                        $validityDays
                    );

            if (
                $today->lt($start)
            ) {
                $usedDays = 0;
            } else {
                $usageEnd =
                    $today->lt($end)
                        ? $today
                        : $end;

                $usedDays =
                    min(
                        $validityDays,
                        max(
                            0,
                            (int)
                            $start
                                ->diffInDays(
                                    $usageEnd
                                )
                        )
                    );
            }

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

            $grossPaid =
                round(
                    (float)
                    Payment::withoutGlobalScopes()
                        ->where(
                            'invoice_id',
                            $dueInvoice->id
                        )
                        ->sum('amount'),
                    2
                );

            $alreadyRefunded =
                round(
                    (float)
                    ClientRefund::withoutGlobalScopes()
                        ->where(
                            'invoice_id',
                            $dueInvoice->id
                        )
                        ->sum('amount'),
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

            $unpaidUsedValue =
                min(
                    round(
                        (float)
                        $dueInvoice
                            ->due_amount,
                        2
                    ),
                    max(
                        0,
                        round(
                            $usedValue
                            - $netPaid,
                            2
                        )
                    )
                );

            $offset =
                round(
                    $offset
                    + $unpaidUsedValue,
                    2
                );

            $adjustments[] = [
                'client_id' =>
                    (int)
                    $owner->id,

                'device_label' =>
                    !$owner
                        ->parent_client_id
                        ? 'Main Device'
                        : (
                            $owner
                                ->device_label
                            ?: 'Device #'
                                . $owner->id
                        ),

                'invoice_id' =>
                    (int)
                    $dueInvoice->id,

                'invoice_no' =>
                    $dueInvoice
                        ->invoice_no,

                'due_amount' =>
                    round(
                        (float)
                        $dueInvoice
                            ->due_amount,
                        2
                    ),

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

                'net_paid' =>
                    $netPaid,

                'unpaid_used_value' =>
                    $unpaidUsedValue,
            ];
        }

        return [
            $offset,
            $adjustments,
            null,
        ];
    }

    /*
     * Execute all paid-device refunds in one batch.
     */
    private function refundAccountDueFamily(
        $familyClients,
        array $preview,
        string $reason
    ): array {
        $batchUuid =
            (string)
            Str::uuid();

        $remaining =
            round(
                (float)
                $preview[
                    'refund_amount'
                ],
                2
            );

        $ledgerReason =
            Str::limit(
                $reason
                . ' | [ACCOUNT_DUE_FAMILY_LOCK_V1] '
                . 'Account-wide due refund: paid devices '
                . count(
                    $preview[
                        'refund_sources'
                    ]
                    ?? []
                )
                . '; gross refundable QAR '
                . number_format(
                    (float)
                    $preview[
                        'base_refund_amount'
                    ],
                    2,
                    '.',
                    ''
                )
                . '; unpaid used service retained QAR '
                . number_format(
                    (float)
                    $preview[
                        'due_usage_offset'
                    ],
                    2,
                    '.',
                    ''
                )
                . '; final refund QAR '
                . number_format(
                    (float)
                    $preview[
                        'refund_amount'
                    ],
                    2,
                    '.',
                    ''
                )
                . '; all devices suspended.',
                1000,
                ''
            );

        $sourceInvoiceIds = [];

        foreach (
            $preview[
                'refund_sources'
            ]
            as $source
        ) {
            $sourceClient =
                $familyClients
                    ->first(
                        fn (Client $row) =>
                            (int) $row->id
                            ===
                            (int)
                            $source[
                                'client_id'
                            ]
                    );

            if (!$sourceClient) {
                throw ValidationException::withMessages([
                    'refund' =>
                        'A refundable device changed. Reopen Refund Service.',
                ]);
            }

            $sourceClient
                ->loadMissing(
                    'package'
                );

            $sourceInvoice =
                Invoice::withoutGlobalScopes()
                    ->where(
                        'client_id',
                        $sourceClient->id
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        (int)
                        $source[
                            'invoice_id'
                        ]
                    );

            $payments =
                Payment::withoutGlobalScopes()
                    ->where(
                        'invoice_id',
                        $sourceInvoice->id
                    )
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

            $fresh =
                $this->calculate(
                    $sourceClient,
                    $sourceInvoice
                );

            if (
                !$fresh['eligible']
                || !$fresh[
                    'period_active'
                ]
            ) {
                throw ValidationException::withMessages([
                    'refund' =>
                        'A paid device refund changed. Reopen Refund Service and review the latest amount.',
                ]);
            }

            $sourceTarget =
                min(
                    $remaining,
                    round(
                        (float)
                        $fresh[
                            'refund_amount'
                        ],
                        2
                    )
                );

            $sourceRemaining =
                $sourceTarget;

            $expiryBefore =
                $sourceClient
                    ->expiry_date
                    ?->toDateString();

            foreach (
                $payments
                as $payment
            ) {
                if (
                    $sourceRemaining <= 0
                ) {
                    break;
                }

                $alreadyRefunded =
                    round(
                        (float)
                        ClientRefund::withoutGlobalScopes()
                            ->where(
                                'payment_id',
                                $payment->id
                            )
                            ->sum('amount'),
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

                if ($available <= 0) {
                    continue;
                }

                $allocation =
                    min(
                        $sourceRemaining,
                        $available
                    );

                ClientRefund::create([
                    'reseller_id' =>
                        $sourceClient
                            ->reseller_id,

                    'batch_uuid' =>
                        $batchUuid,

                    'client_id' =>
                        $sourceClient->id,

                    'invoice_id' =>
                        $sourceInvoice->id,

                    'payment_id' =>
                        $payment->id,

                    'amount' =>
                        $allocation,

                    'refund_date' =>
                        Carbon::today(
                            'Asia/Qatar'
                        )->toDateString(),

                    'validity_days' =>
                        $fresh[
                            'validity_days'
                        ],

                    'used_days' =>
                        $fresh[
                            'used_days'
                        ],

                    'daily_rate' =>
                        $fresh[
                            'daily_rate'
                        ],

                    'service_price' =>
                        $fresh[
                            'service_price'
                        ],

                    'used_value' =>
                        $fresh[
                            'used_value'
                        ],

                    'unused_value' =>
                        $fresh[
                            'unused_value'
                        ],

                    'service_start_date' =>
                        $fresh[
                            'service_start_date'
                        ],

                    'service_end_date' =>
                        $fresh[
                            'service_end_date'
                        ],

                    'client_expiry_before' =>
                        $expiryBefore,

                    'reason' =>
                        $ledgerReason,

                    'refunded_by' =>
                        auth()->id(),
                ]);

                $sourceRemaining =
                    round(
                        $sourceRemaining
                        - $allocation,
                        2
                    );

                $remaining =
                    round(
                        $remaining
                        - $allocation,
                        2
                    );
            }

            if (
                $sourceRemaining > 0.009
            ) {
                throw ValidationException::withMessages([
                    'refund' =>
                        'Refund allocation changed safely. Reopen Refund Service and try again.',
                ]);
            }

            $totalRefunded =
                round(
                    (float)
                    ClientRefund::withoutGlobalScopes()
                        ->where(
                            'invoice_id',
                            $sourceInvoice->id
                        )
                        ->sum('amount'),
                    2
                );

            $grossPaid =
                round(
                    (float)
                    Payment::withoutGlobalScopes()
                        ->where(
                            'invoice_id',
                            $sourceInvoice->id
                        )
                        ->sum('amount'),
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

            $sourceInvoice
                ->forceFill([
                    'paid_amount' =>
                        $netRetained,

                    'due_amount' => 0,

                    'status' =>
                        'refunded',

                    'refunded_amount' =>
                        $totalRefunded,

                    'service_cancelled_at' =>
                        Carbon::now(
                            'Asia/Qatar'
                        ),
                ])
                ->save();

            $sourceInvoiceIds[] =
                (int)
                $sourceInvoice->id;
        }

        if ($remaining > 0.009) {
            throw ValidationException::withMessages([
                'refund' =>
                    'Account refund allocation failed safely. No partial account refund was saved.',
            ]);
        }

        $dueInvoiceIds =
            collect(
                $preview[
                    'due_adjustments'
                ]
                ?? []
            )
                ->pluck(
                    'invoice_id'
                )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->filter(
                    fn ($id) =>
                        $id > 0
                )
                ->diff(
                    $sourceInvoiceIds
                )
                ->unique()
                ->values();

        if (
            $dueInvoiceIds
                ->isNotEmpty()
        ) {
            Invoice::withoutGlobalScopes()
                ->whereIn(
                    'id',
                    $dueInvoiceIds
                )
                ->whereNull(
                    'service_cancelled_at'
                )
                ->update([
                    'due_amount' => 0,
                    'status' =>
                        'cancelled',

                    'service_cancelled_at' =>
                        Carbon::now(
                            'Asia/Qatar'
                        ),
                ]);
        }

        $familyClientIds =
            collect(
                $preview[
                    'family_client_ids'
                ]
                ?? []
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values();

        Client::withoutGlobalScopes()
            ->whereIn(
                'id',
                $familyClientIds
            )
            ->update([
                'enabled' => false,
                'connected' => false,

                'expiry_date' =>
                    Carbon::today(
                        'Asia/Qatar'
                    )->toDateString(),
            ]);

        return [
            ...$preview,

            'batch_uuid' =>
                $batchUuid,

            'suspend_client_ids' =>
                $familyClientIds
                    ->all(),
        ];
    }

    private function familyClients(
        Client $client,
        bool $lock = false
    ) {
        $primaryId =
            (int) (
                $client
                    ->parent_client_id
                ?: $client->id
            );

        $query =
            Client::withoutGlobalScopes()
                ->with(
                    'package'
                )
                ->whereNull(
                    'deleted_at'
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $primaryId
                    ): void {
                        $query
                            ->whereKey(
                                $primaryId
                            )
                            ->orWhere(
                                'parent_client_id',
                                $primaryId
                            );
                    }
                )
                ->orderBy('id');

        if (
            $client
                ->reseller_id
            === null
        ) {
            $query
                ->whereNull(
                    'reseller_id'
                );
        } else {
            $query->where(
                'reseller_id',
                $client
                    ->reseller_id
            );
        }

        if (
            $client
                ->zone_id
            === null
        ) {
            $query
                ->whereNull(
                    'zone_id'
                );
        } else {
            $query->where(
                'zone_id',
                $client
                    ->zone_id
            );
        }

        if ($lock) {
            $query
                ->lockForUpdate();
        }

        return $query
            ->get();
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
