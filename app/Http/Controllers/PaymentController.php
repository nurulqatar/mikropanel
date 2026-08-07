<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ClientProvisionService;
use App\Services\InvoiceServicePeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PaymentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Payments/Index', [
            'payments' => Payment::with([
                'client',
                'invoice',
                'receiver:id,name',
            ])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Payments/Create', [
            'clients' => Client::orderBy('name')
                ->get([
                    'id',
                    'name',
                    'client_code',
                ]),

            'invoices' => Invoice::where(
                'status',
                '!=',
                'paid'
            )
                ->with('client')
                ->latest()
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        ClientProvisionService $provision,
        InvoiceServicePeriodService $servicePeriods
    ): RedirectResponse {
        $data = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                'exists:invoices,id',
            ],

            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'payment_date' => [
                'required',
                'date',
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

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        /*
         * Payment এবং Invoice update একই database
         * transaction-এর মধ্যে হবে।
         */
        $clientIdToActivate = DB::transaction(
            function () use (
                $data,
                $servicePeriods
            ): ?int {
                $invoice = Invoice::query()
                    ->lockForUpdate()
                    ->findOrFail($data['invoice_id']);

                /*
                 * Selected invoice অবশ্যই selected
                 * client-এর হতে হবে।
                 */
                if (
                    (int) $invoice->client_id !==
                    (int) $data['client_id']
                ) {
                    throw ValidationException::withMessages([
                        'invoice_id' =>
                            'The selected invoice does not belong to this client.',
                    ]);
                }

                $netAmount = max(
                    0,
                    round(
                        (float) $invoice->amount
                        - (float) $invoice->discount,
                        2
                    )
                );

                /*
                 * Payment table থেকে actual paid amount
                 * হিসাব করা হচ্ছে।
                 */
                $alreadyPaid = round(
                    (float) Payment::where(
                        'invoice_id',
                        $invoice->id
                    )->sum('amount'),
                    2
                );

                $remainingDue = max(
                    0,
                    round(
                        $netAmount - $alreadyPaid,
                        2
                    )
                );

                $paymentAmount = round(
                    (float) $data['amount'],
                    2
                );

                if ($remainingDue <= 0) {
                    throw ValidationException::withMessages([
                        'invoice_id' =>
                            'This invoice is already fully paid.',
                    ]);
                }

                if ($paymentAmount > $remainingDue) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Payment cannot exceed the remaining due amount of '
                            . number_format($remainingDue, 2)
                            . '.',
                    ]);
                }

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'amount' => $paymentAmount,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'transaction_id' =>
                        $data['transaction_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'received_by' => auth()->id(),
                ]);

                $newPaidAmount = round(
                    $alreadyPaid + $paymentAmount,
                    2
                );

                $newDueAmount = max(
                    0,
                    round(
                        $netAmount - $newPaidAmount,
                        2
                    )
                );

                $newStatus = $this->resolveInvoiceStatus(
                    $invoice,
                    $newPaidAmount,
                    $newDueAmount
                );

                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'due_amount' => $newDueAmount,
                    'status' => $newStatus,
                ]);

                /*
                 * Fully-paid service invoice applies
                 * exactly one service period.
                 *
                 * InvoiceServicePeriodService contains
                 * the idempotency protection.
                 */
                if ($newStatus !== 'paid') {
                    return null;
                }

                return $servicePeriods
                    ->applyIfNeeded(
                        $invoice
                    );
            }
        );

        /*
         * Database transaction শেষ হওয়ার পরে
         * MikroTik API call হবে।
         */
        if ($clientIdToActivate) {
            try {
                $client = Client::with([
                    'router',
                    'package',
                    'ipRange',
                ])->findOrFail($clientIdToActivate);

                $provision->unsuspend($client);
            } catch (Throwable $exception) {
                Log::error(
                    'Payment saved but MikroTik activation failed.',
                    [
                        'client_id' => $clientIdToActivate,
                        'message' => $exception->getMessage(),
                    ]
                );

                return redirect()
                    ->route('payments.index')
                    ->with(
                        'error',
                        'Payment saved and expiry renewed, but MikroTik activation failed. Check the router connection.'
                    );
            }

            return redirect()
                ->route('payments.index')
                ->with(
                    'success',
                    'Payment received, expiry renewed and client activated successfully.'
                );
        }

        return redirect()
            ->route('payments.index')
            ->with(
                'success',
                'Partial payment received successfully. Client will activate after the invoice is fully paid.'
            );
    }

    public function show(Payment $payment): RedirectResponse
    {
        return redirect()->route('payments.index');
    }

    public function edit(Payment $payment): RedirectResponse
    {
        return redirect()->route('payments.index');
    }

    public function update(
        Request $request,
        Payment $payment
    ): RedirectResponse {
        return redirect()->route('payments.index');
    }

    public function destroy(
        Payment $payment
    ): RedirectResponse {
        DB::transaction(function () use ($payment): void {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($lockedPayment->invoice_id);

            $lockedPayment->delete();

            $this->recalculateInvoice($invoice);
        });

        return back()->with(
            'success',
            'Payment deleted and invoice totals recalculated successfully.'
        );
    }

    private function recalculateInvoice(
        Invoice $invoice
    ): void {
        $paidAmount = round(
            (float) Payment::where(
                'invoice_id',
                $invoice->id
            )->sum('amount'),
            2
        );

        $netAmount = max(
            0,
            round(
                (float) $invoice->amount
                - (float) $invoice->discount,
                2
            )
        );

        $dueAmount = max(
            0,
            round(
                $netAmount - $paidAmount,
                2
            )
        );

        $invoice->update([
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'status' => $this->resolveInvoiceStatus(
                $invoice,
                $paidAmount,
                $dueAmount
            ),
        ]);
    }

    private function resolveInvoiceStatus(
        Invoice $invoice,
        float $paidAmount,
        float $dueAmount
    ): string {
        if ($dueAmount <= 0) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial';
        }

        if (
            $invoice->due_date
            && $invoice->due_date->isBefore(today())
        ) {
            return 'unpaid';
        }

        return 'unpaid';
    }
}
