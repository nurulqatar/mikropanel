<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Invoices/Index', [
            'invoices' => Invoice::with('client')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Invoices/Create', [
            'clients' => Client::orderBy('name')->get([
                'id',
                'client_code',
                'name',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'billing_month'  => ['required', 'date'],
            'amount'         => ['required', 'numeric'],
            'discount'       => ['nullable', 'numeric'],
            'issue_date'     => ['required', 'date'],
            'due_date'       => ['required', 'date'],
            'notes'          => ['nullable', 'string'],
        ]);

        $last = Invoice::max('id') ?? 0;

        $data['invoice_no'] = 'INV-' .
            str_pad($last + 1, 6, '0', STR_PAD_LEFT);

        $data['discount'] = $data['discount'] ?? 0;

        $data['paid_amount'] = 0;

        $data['due_amount'] =
            $data['amount'] - $data['discount'];

        $data['status'] = 'unpaid';

        $data['created_by'] = auth()->id();

        Invoice::create($data);

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Invoice created successfully.');
    }

    public function edit(Invoice $invoice): Response
    {
        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'clients' => Client::orderBy('name')->get([
                'id',
                'client_code',
                'name',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Invoice $invoice
    ): RedirectResponse {

        $data = $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'billing_month'  => ['required', 'date'],
            'amount'         => ['required', 'numeric'],
            'discount'       => ['nullable', 'numeric'],
            'issue_date'     => ['required', 'date'],
            'due_date'       => ['required', 'date'],
            'status'         => ['required'],
            'notes'          => ['nullable', 'string'],
        ]);

        $data['discount'] = $data['discount'] ?? 0;

        $invoice->update($data);

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(
        Invoice $invoice
    ): RedirectResponse {

        $invoice->delete();

        return back()->with(
            'success',
            'Invoice deleted successfully.'
        );
    }
}
