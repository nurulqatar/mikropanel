<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceDocumentController extends Controller
{
    public function print(
        Invoice $invoice
    ): View {
        $invoices = $this->getInvoices(
            $invoice
        );

        return $this->printView(
            $invoices,
            $invoice->invoice_no
        );
    }

    public function download(
        Invoice $invoice
    ): Response {
        $invoices = $this->getInvoices(
            $invoice
        );

        $pdf = $this->makePdf(
            $invoices,
            $invoice->invoice_no
        );

        $fileName = Str::slug(
            $invoice->invoice_no
                ?: 'invoice-' . $invoice->id
        ) . '.pdf';

        return $pdf->download($fileName);
    }

    public function printAll(): View
    {
        $invoices = $this->getInvoices();

        return $this->printView(
            $invoices,
            'All Invoices'
        );
    }

    public function downloadAll(): Response
    {
        $invoices = $this->getInvoices();

        return $this->makePdf(
            $invoices,
            'All Invoices'
        )->download(
            'genius-invoices-'
            . today()->format('Y-m-d')
            . '.pdf'
        );
    }

    public function printClient(
        Client $client
    ): View {
        $invoices = $this->getClientInvoices(
            $client
        );

        return $this->printView(
            $invoices,
            "Invoice Report - {$client->name}"
        );
    }

    public function downloadClient(
        Client $client
    ): Response {
        $invoices = $this->getClientInvoices(
            $client
        );

        $fileName = Str::slug(
            $client->client_code
            . '-'
            . $client->name
            . '-invoice-report'
        ) . '.pdf';

        return $this->makePdf(
            $invoices,
            "Invoice Report - {$client->name}"
        )->download($fileName);
    }

    private function printView(
        Collection $invoices,
        string $documentTitle
    ): View {
        return view('invoices.document', [
            'invoices' => $invoices,
            'paymentsByInvoice' =>
                $this->getPayments($invoices),

            'pdfMode' => false,
            'documentTitle' => $documentTitle,
        ]);
    }

    private function makePdf(
        Collection $invoices,
        string $documentTitle
    ) {
        return Pdf::loadView(
            'invoices.document',
            [
                'invoices' => $invoices,
                'paymentsByInvoice' =>
                    $this->getPayments($invoices),

                'pdfMode' => true,
                'documentTitle' =>
                    $documentTitle,
            ]
        )
            ->setPaper('a4', 'portrait')
            ->setOption('dpi', 96)
            ->setOption(
                'defaultFont',
                'DejaVu Sans'
            )
            ->setOption(
                'isHtml5ParserEnabled',
                true
            );
    }

    private function getInvoices(
        ?Invoice $invoice = null
    ): Collection {
        $query = Invoice::query()
            ->with('client.package');

        if ($invoice) {
            return $query
                ->whereKey($invoice->id)
                ->get();
        }

        return $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    private function getClientInvoices(
        Client $client
    ): Collection {
        return Invoice::query()
            ->where('client_id', $client->id)
            ->with('client.package')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    private function getPayments(
        Collection $invoices
    ): Collection {
        if ($invoices->isEmpty()) {
            return collect();
        }

        return Payment::query()
            ->whereIn(
                'invoice_id',
                $invoices->pluck('id')
            )
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->groupBy('invoice_id');
    }
}
