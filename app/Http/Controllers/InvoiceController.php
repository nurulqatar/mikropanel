<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Invoices/Index', [
            'invoices' => Invoice::with([
                'client:id,name,client_code',
            ])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Invoices/Create', [
            'clients' => Client::with([
                'package:id,name,price',
            ])
                ->orderBy('name')
                ->get([
                    'id',
                    'package_id',
                    'name',
                    'client_code',
                    'enabled',
                ]),
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'billing_month' => [
                'required',
                'date',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:amount',
            ],

            'issue_date' => [
                'required',
                'date',
            ],

            'due_date' => [
                'required',
                'date',
                'after_or_equal:issue_date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $invoice = DB::transaction(function () use ($data) {
            $amount = round(
                (float) $data['amount'],
                2
            );

            $discount = round(
                (float) ($data['discount'] ?? 0),
                2
            );

            $dueAmount = max(
                0,
                round($amount - $discount, 2)
            );

            $lastId = Invoice::query()->max('id') ?? 0;

            $invoiceNo = 'INV-' . str_pad(
                $lastId + 1,
                5,
                '0',
                STR_PAD_LEFT
            );

            return Invoice::create([
                'client_id' => $data['client_id'],
                'invoice_no' => $invoiceNo,
                'billing_month' => $data['billing_month'],
                'amount' => $amount,
                'discount' => $discount,
                'paid_amount' => 0,
                'due_amount' => $dueAmount,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => $dueAmount <= 0
                    ? 'paid'
                    : 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('invoices.index')
            ->with(
                'success',
                "Invoice {$invoice->invoice_no} created successfully."
            );
    }

    public function show(
        Invoice $invoice
    ): RedirectResponse {
        return redirect()
            ->route('invoices.index');
    }

    public function edit(
        Invoice $invoice
    ): Response {
        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,

            'clients' => Client::orderBy('name')
                ->get([
                    'id',
                    'name',
                    'client_code',
                ]),
        ]);
    }

    public function update(
        Request $request,
        Invoice $invoice
    ): RedirectResponse {
        $data = $request->validate([
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'billing_month' => [
                'required',
                'date',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:amount',
            ],

            'issue_date' => [
                'required',
                'date',
            ],

            'due_date' => [
                'required',
                'date',
                'after_or_equal:issue_date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use (
            $invoice,
            $data
        ): void {
            $amount = round(
                (float) $data['amount'],
                2
            );

            $discount = round(
                (float) ($data['discount'] ?? 0),
                2
            );

            $paidAmount = round(
                (float) Payment::where(
                    'invoice_id',
                    $invoice->id
                )->sum('amount'),
                2
            );

            $dueAmount = max(
                0,
                round(
                    $amount
                    - $discount
                    - $paidAmount,
                    2
                )
            );

            if ($dueAmount <= 0) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
            } elseif (
                now()->startOfDay()->greaterThan(
                    \Carbon\Carbon::parse(
                        $data['due_date']
                    )->startOfDay()
                )
            ) {
                $status = 'unpaid';
            } else {
                $status = 'unpaid';
            }

            $invoice->update([
                'client_id' => $data['client_id'],
                'billing_month' =>
                    $data['billing_month'],
                'amount' => $amount,
                'discount' => $discount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('invoices.index')
            ->with(
                'success',
                'Invoice updated successfully.'
            );
    }

    public function destroy(
        Invoice $invoice
    ): RedirectResponse {
        if (
            Payment::where(
                'invoice_id',
                $invoice->id
            )->exists()
        ) {
            return back()->with(
                'error',
                'Cannot delete an invoice that has payments.'
            );
        }

        $invoice->delete();

        return back()->with(
            'success',
            'Invoice deleted successfully.'
        );
    }
}
