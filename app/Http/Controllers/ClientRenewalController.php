<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ClientProvisionService;
use App\Services\InvoiceServicePeriodService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClientRenewalController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        ClientProvisionService $provision,
        InvoiceServicePeriodService $servicePeriods
    ): RedirectResponse {
        $data = $request->validate([
            'received_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],

            'payment_method' => [
                'required',
                'string',
                Rule::in([
                    'Cash',
                    'Bank Transfer',
                    'bKash',
                    'Nagad',
                    'Rocket',
                    'Upay',
                    'Ooredoo Money',
                    'iPay',
                    'Stripe',
                    'PayPal',
                    'Manual Adjustment',
                ]),
            ],

            'transaction_id' => [
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

        $result = DB::transaction(
            function () use (
                $client,
                $data,
                $servicePeriods
            ): array {
                $lockedClient = Client::query()
                    ->with('package')
                    ->lockForUpdate()
                    ->findOrFail($client->id);

                $paymentDate = Carbon::today(
                    'Asia/Qatar'
                );

                /*
                 * Client-এর পুরোনো due invoiceগুলো
                 * oldest invoice থেকে আগে নেওয়া হবে।
                 */
                $dueInvoices = Invoice::query()
                    ->where(
                        'client_id',
                        $lockedClient->id
                    )
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
                    ->orderBy('issue_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $totalDue = round(
                    (float) $dueInvoices->sum(
                        function (
                            Invoice $invoice
                        ): float {
                            $netAmount = max(
                                0,
                                round(
                                    (float) $invoice->amount
                                    - (float) $invoice->discount,
                                    2
                                )
                            );

                            $actualPaid = round(
                                (float) Payment::query()
                                    ->where(
                                        'invoice_id',
                                        $invoice->id
                                    )
                                    ->sum('amount'),
                                2
                            );

                            return max(
                                0,
                                round(
                                    $netAmount
                                    - $actualPaid,
                                    2
                                )
                            );
                        }
                    ),
                    2
                );

                /*
                 * Due থাকলে একই button শুধু Due Payment
                 * গ্রহণ করবে। Expiry আবার বাড়বে না।
                 */
                if ($totalDue > 0) {
                    return $this->receiveDuePayment(
                        $lockedClient,
                        $dueInvoices,
                        $totalDue,
                        $data,
                        $paymentDate,
                        $servicePeriods
                    );
                }

                /*
                 * Due না থাকলে নতুন মাসের Pay & Renew।
                 */
                return $this->renewClient(
                    $lockedClient,
                    $data,
                    $paymentDate
                );
            }
        );

        /*
         * শুধু নতুন renewal-এর সময় MikroTik activate হবে।
         * পুরোনো due payment-এ দ্বিতীয়বার renew হবে না।
         */
        if (
            $result['mode'] === 'renewal'
            || (
                $result['service_applied']
                ?? false
            )
        ) {
            try {
                $renewedClient = Client::with([
                    'router',
                    'package',
                    'ipRange',
                ])->findOrFail(
                    $result['client_id']
                );

                $provision->unsuspend(
                    $renewedClient
                );
            } catch (Throwable $exception) {
                Log::error(
                    'Renewal saved but MikroTik activation failed.',
                    [
                        'client_id' =>
                            $result['client_id'],

                        'message' =>
                            $exception->getMessage(),
                    ]
                );

                return back()->withErrors([
                    'renewal' =>
                        'Renewal and payment were saved, but MikroTik activation failed. Do not submit payment again. Check the router and use Activate.',
                ]);
            }

            if ($result['remaining_due'] > 0) {
                return back()->with(
                    'success',
                    'Client renewed successfully. Received QAR '
                    . number_format(
                        $result['received_amount'],
                        2
                    )
                    . ', remaining due QAR '
                    . number_format(
                        $result['remaining_due'],
                        2
                    )
                    . '.'
                );
            }

            return back()->with(
                'success',
                'Full payment received and client renewed successfully.'
            );
        }

        if ($result['remaining_due'] > 0) {
            return back()->with(
                'success',
                'Due payment received successfully. Remaining due QAR '
                . number_format(
                    $result['remaining_due'],
                    2
                )
                . '.'
            );
        }

        return back()->with(
            'success',
            'All previous due has been paid successfully.'
        );
    }

    private function receiveDuePayment(
        Client $client,
        $dueInvoices,
        float $totalDue,
        array $data,
        Carbon $paymentDate,
        InvoiceServicePeriodService $servicePeriods
    ): array {
        $receivedAmount = round(
            (float) $data['received_amount'],
            2
        );

        if ($receivedAmount < 0.01) {
            throw ValidationException::withMessages([
                'received_amount' =>
                    'Enter the amount received from the client.',
            ]);
        }

        if ($receivedAmount > $totalDue) {
            throw ValidationException::withMessages([
                'received_amount' =>
                    'Payment cannot exceed the total due amount of QAR '
                    . number_format($totalDue, 2)
                    . '.',
            ]);
        }

        $remainingPayment = $receivedAmount;
        $serviceApplied = false;

        foreach ($dueInvoices as $invoice) {
            if ($remainingPayment <= 0) {
                break;
            }

            $netAmount = max(
                0,
                round(
                    (float) $invoice->amount
                    - (float) $invoice->discount,
                    2
                )
            );

            $alreadyPaid = round(
                (float) Payment::query()
                    ->where(
                        'invoice_id',
                        $invoice->id
                    )
                    ->sum('amount'),
                2
            );

            $invoiceDue = max(
                0,
                round(
                    $netAmount - $alreadyPaid,
                    2
                )
            );

            if ($invoiceDue <= 0) {
                continue;
            }

            $appliedAmount = min(
                $remainingPayment,
                $invoiceDue
            );

            Payment::create([
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'amount' => $appliedAmount,
                'payment_date' =>
                    $paymentDate->toDateString(),

                'payment_method' =>
                    $data['payment_method'],

                'transaction_id' =>
                    $data['transaction_id'] ?? null,

                'notes' =>
                    $data['notes']
                    ?? 'Quick Due Payment',

                'received_by' => auth()->id(),
            ]);

            $newPaidAmount = round(
                $alreadyPaid + $appliedAmount,
                2
            );

            $newDueAmount = max(
                0,
                round(
                    $netAmount - $newPaidAmount,
                    2
                )
            );

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'due_amount' => $newDueAmount,

                'status' => $newDueAmount <= 0
                    ? 'paid'
                    : 'partial',
            ]);

            if ($newDueAmount <= 0) {
                $appliedClientId =
                    $servicePeriods
                        ->applyIfNeeded(
                            $invoice
                        );

                if ($appliedClientId) {
                    $serviceApplied = true;
                }
            }

            $remainingPayment = round(
                $remainingPayment
                - $appliedAmount,
                2
            );
        }

        return [
            'mode' => 'due_payment',
            'client_id' => $client->id,
            'received_amount' =>
                $receivedAmount,

            'service_applied' =>
                $serviceApplied,

            'remaining_due' => max(
                0,
                round(
                    $totalDue
                    - $receivedAmount,
                    2
                )
            ),
        ];
    }

    private function renewClient(
        Client $client,
        array $data,
        Carbon $paymentDate
    ): array {
        $package = $client->package;

        if (!$package) {
            throw ValidationException::withMessages([
                'renewal' =>
                    'This client does not have a package.',
            ]);
        }

        $price = round(
            (float) $package->price,
            2
        );

        $validityDays = (int)
            $package->validity_days;

        if ($price <= 0) {
            throw ValidationException::withMessages([
                'renewal' =>
                    'Package price must be greater than zero.',
            ]);
        }

        if ($validityDays < 1) {
            throw ValidationException::withMessages([
                'renewal' =>
                    'Package validity days are missing.',
            ]);
        }

        $receivedAmount = round(
            (float) $data['received_amount'],
            2
        );

        if ($receivedAmount > $price) {
            throw ValidationException::withMessages([
                'received_amount' =>
                    'Received amount cannot exceed the package bill of QAR '
                    . number_format($price, 2)
                    . '.',
            ]);
        }

        $remainingDue = max(
            0,
            round(
                $price - $receivedAmount,
                2
            )
        );

        $status = $remainingDue <= 0
            ? 'paid'
            : (
                $receivedAmount > 0
                    ? 'partial'
                    : 'unpaid'
            );

        $currentExpiry = $client->expiry_date
            ? $client->expiry_date
                ->copy()
                ->startOfDay()
            : null;

        $startFromToday =
            !$client->enabled
            || !$currentExpiry
            || $currentExpiry->lt(
                $paymentDate
            );

        $baseDate = $startFromToday
            ? $paymentDate
            : $currentExpiry;

        /*
         * 30 days package calendar month হিসাবে।
         */
        $newExpiry = $validityDays === 30
            ? $baseDate
                ->copy()
                ->addMonthNoOverflow()
            : $baseDate
                ->copy()
                ->addDays($validityDays);

        $invoiceNo = 'INV-'
            . Carbon::now('Asia/Qatar')
                ->format('YmdHis')
            . '-'
            . $client->id
            . '-'
            . Str::upper(
                Str::random(4)
            );

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'invoice_no' => $invoiceNo,

            'billing_month' => $paymentDate
                ->copy()
                ->startOfMonth()
                ->toDateString(),

            'amount' => $price,
            'discount' => 0,
            'paid_amount' => $receivedAmount,
            'due_amount' => $remainingDue,

            'issue_date' =>
                $paymentDate->toDateString(),

            'due_date' =>
                $paymentDate->toDateString(),

            'status' => $status,

            'notes' =>
                $data['notes']
                ?? (
                    $remainingDue > 0
                        ? 'Quick Pay & Renew with due'
                        : 'Quick Pay & Renew'
                ),

            'created_by' => auth()->id(),

            /*
             * Quick Renew already extends the
             * client's expiry immediately.
             * Mark this invoice as applied so
             * later due payment cannot renew
             * the same period twice.
             */
            'applies_service_period' => true,
            'service_applied_at' =>
                Carbon::now(
                    'Asia/Qatar'
                ),
        ]);

        /*
         * Received amount 0 হলে payment record হবে না।
         * Invoice-এর পুরো amount Due থাকবে।
         */
        if ($receivedAmount > 0) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'amount' => $receivedAmount,

                'payment_date' =>
                    $paymentDate->toDateString(),

                'payment_method' =>
                    $data['payment_method'],

                'transaction_id' =>
                    $data['transaction_id'] ?? null,

                'notes' =>
                    $data['notes']
                    ?? 'Quick Pay & Renew',

                'received_by' => auth()->id(),
            ]);
        }

        $client->update([
            'expiry_date' =>
                $newExpiry->toDateString(),
        ]);

        return [
            'mode' => 'renewal',
            'client_id' => $client->id,
            'received_amount' =>
                $receivedAmount,

            'remaining_due' =>
                $remainingDue,
        ];
    }
}
