<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class InvoiceServicePeriodService
{
    public function applyIfNeeded(
        Invoice $invoice
    ): ?int {
        /*
         * Only service invoices may extend
         * customer internet validity.
         */
        if (
            !$invoice->applies_service_period
        ) {
            return null;
        }

        /*
         * Idempotency guard:
         * the same invoice can never extend
         * expiry twice.
         */
        if ($invoice->service_applied_at) {
            return null;
        }

        if (
            $invoice->status !== 'paid'
            || (float) $invoice->due_amount > 0
        ) {
            return null;
        }

        $client = Client::query()
            ->with('package')
            ->lockForUpdate()
            ->findOrFail(
                $invoice->client_id
            );

        $validityDays = (int) (
            $client->package
                ?->validity_days
            ?? 0
        );

        if ($validityDays < 1) {
            throw ValidationException::withMessages([
                'invoice_id' =>
                    'The client package validity days are missing.',
            ]);
        }

        $today = Carbon::today(
            'Asia/Qatar'
        );

        $currentExpiry =
            $client->expiry_date
                ?->copy()
                ->startOfDay();

        $baseDate =
            $currentExpiry
            && $currentExpiry
                ->greaterThanOrEqualTo(
                    $today
                )
                ? $currentExpiry
                : $today;

        /*
         * Preserve existing MikroPanel rule:
         * a 30-day package means one calendar
         * month, not always exactly 30 days.
         */
        $newExpiry =
            $validityDays === 30
                ? $baseDate
                    ->copy()
                    ->addMonthNoOverflow()
                : $baseDate
                    ->copy()
                    ->addDays(
                        $validityDays
                    );

        $client->update([
            'expiry_date' =>
                $newExpiry
                    ->toDateString(),
        ]);

        $invoice->forceFill([
            'service_applied_at' =>
                Carbon::now(
                    'Asia/Qatar'
                ),
        ])->save();

        return $client->id;
    }
}
