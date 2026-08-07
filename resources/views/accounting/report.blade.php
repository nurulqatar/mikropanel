<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        {{ $reportTitle }}
    </title>

    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1e293b;
            font-size: 9px;
            line-height: 1.4;
        }

        body.screen-mode {
            background: #e2e8f0;
            padding: 20px;
        }

        .toolbar {
            max-width: 1120px;
            margin: 0 auto 15px;
            padding: 12px;
            background: #ffffff;
            border-radius: 8px;
            text-align: right;
        }

        .toolbar button,
        .toolbar a {
            display: inline-block;
            margin-left: 6px;
            padding: 9px 14px;
            border: 0;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .print-button {
            background: #0891b2;
            color: #ffffff;
        }

        .back-button {
            background: #475569;
            color: #ffffff;
        }

        .report-page {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            background: #ffffff;
        }

        body.screen-mode .report-page {
            max-width: 1120px;
            padding: 20px;
            border-radius: 10px;
        }

        body.pdf-mode .report-page {
            margin: 0;
            padding: 0;
            border: 0;
        }

        .header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            margin: 0;
            color: #0e7490;
            font-size: 20px;
            font-weight: bold;
        }

        .company-details {
            margin-top: 4px;
            color: #475569;
            line-height: 1.6;
        }

        .report-title {
            margin: 0;
            text-align: right;
            color: #0f172a;
            font-size: 18px;
            font-weight: bold;
        }

        .report-period {
            margin-top: 5px;
            text-align: right;
            color: #64748b;
        }

        .summary-grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 5px;
            margin: 0 -5px 14px;
        }

        .summary-card {
            padding: 9px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #f8fafc;
            vertical-align: top;
        }

        .summary-label {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .summary-value {
            margin-top: 4px;
            color: #0f172a;
            font-size: 15px;
            font-weight: bold;
        }

        .positive {
            color: #047857;
        }

        .negative {
            color: #dc2626;
        }

        .section {
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .section.new-page {
            page-break-before: always;
        }

        .section-title {
            margin: 0 0 7px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0891b2;
            color: #0f172a;
            font-size: 13px;
        }

        table.report-table {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 5px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }

        .report-table th {
            background: #e2e8f0;
            color: #334155;
            font-size: 8px;
            text-transform: uppercase;
            text-align: left;
        }

        .report-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .muted {
            color: #64748b;
        }

        .status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            background: #e2e8f0;
            font-size: 8px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status-paid {
            background: #d1fae5;
            color: #047857;
        }

        .status-partial {
            background: #fef3c7;
            color: #b45309;
        }

        .status-unpaid,
        .status-overdue {
            background: #fee2e2;
            color: #b91c1c;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td:last-child {
            text-align: right;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .report-page {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
                border: 0;
            }
        }
    </style>
</head>

<body class="{{ $pdfMode ? 'pdf-mode' : 'screen-mode' }}">
    @unless($pdfMode)
        <div class="toolbar">
            <a
                href="{{ route('accounting.index', [
                    'preset' => $filters['preset'],
                    'start_date' => $filters['start_date'],
                    'end_date' => $filters['end_date'],
                ]) }}"
                class="back-button"
            >
                Back
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="print-button"
            >
                Print Report
            </button>
        </div>
    @endunless

    <main class="report-page">
        <table class="header-table">
            <tr>
                <td style="width: 58%;">
                    <h1 class="company-name">
                        {{ $company['name'] }}
                    </h1>

                    <div class="company-details">
                        Mobile:
                        {{ $company['mobile'] }}
                        <br>

                        Email:
                        {{ $company['email'] }}
                    </div>
                </td>

                <td style="width: 42%;">
                    <h2 class="report-title">
                        {{ $reportTitle }}
                    </h2>

                    <div class="report-period">
                        Period:
                        {{ $filters['label'] }}
                        <br>

                        Generated:
                        {{ $generatedAt }}
                    </div>
                </td>
            </tr>
        </table>

        <table class="summary-grid">
            <tr>
                <td class="summary-card">
                    <div class="summary-label">
                        Cash Collection
                    </div>

                    <div class="summary-value positive">
                        QAR
                        {{ number_format($summary['collection'], 2) }}
                    </div>
                </td>

                <td class="summary-card">
                    <div class="summary-label">
                        Expenses
                    </div>

                    <div class="summary-value negative">
                        QAR
                        {{ number_format($summary['expenses'], 2) }}
                    </div>
                </td>

                <td class="summary-card">
                    <div class="summary-label">
                        Net Profit / Loss
                    </div>

                    <div class="summary-value {{ $summary['net_profit'] >= 0 ? 'positive' : 'negative' }}">
                        QAR
                        {{ number_format($summary['net_profit'], 2) }}
                    </div>
                </td>

                <td class="summary-card">
                    <div class="summary-label">
                        Net Billed
                    </div>

                    <div class="summary-value">
                        QAR
                        {{ number_format($summary['net_billed'], 2) }}
                    </div>
                </td>

                <td class="summary-card">
                    <div class="summary-label">
                        Customer Due
                    </div>

                    <div class="summary-value negative">
                        QAR
                        {{ number_format($summary['current_receivable'], 2) }}
                    </div>
                </td>

                <td class="summary-card">
                    <div class="summary-label">
                        Overdue
                    </div>

                    <div class="summary-value negative">
                        QAR
                        {{ number_format($summary['overdue_amount'], 2) }}
                    </div>
                </td>
            </tr>
        </table>

        @if(
            $report === 'full'
            || $report === 'profit-loss'
        )
            <section class="section">
                <h3 class="section-title">
                    Profit and Loss Summary
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th style="width: 25%;" class="text-right">
                                Amount
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>
                                Cash Collection
                            </td>

                            <td class="text-right positive bold">
                                QAR
                                {{ number_format($summary['collection'], 2) }}
                            </td>
                        </tr>

                        <tr>
                            <td>
                                Less: Business Expenses
                            </td>

                            <td class="text-right negative bold">
                                QAR
                                {{ number_format($summary['expenses'], 2) }}
                            </td>
                        </tr>

                        <tr>
                            <td class="bold">
                                {{ $summary['net_profit'] >= 0 ? 'Net Profit' : 'Net Loss' }}
                            </td>

                            <td class="text-right bold {{ $summary['net_profit'] >= 0 ? 'positive' : 'negative' }}">
                                QAR
                                {{ number_format(abs($summary['net_profit']), 2) }}
                            </td>
                        </tr>

                        <tr>
                            <td>
                                Cash Profit Margin
                            </td>

                            <td class="text-right bold">
                                {{ number_format($summary['profit_margin'], 2) }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h3 class="section-title">
                    Monthly Financial Trend
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-right">Net Billed</th>
                            <th class="text-right">Collection</th>
                            <th class="text-right">Expenses</th>
                            <th class="text-right">Profit / Loss</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($monthlyTrend as $row)
                            <tr>
                                <td class="bold">
                                    {{ $row['label'] }}
                                </td>

                                <td class="text-right">
                                    QAR
                                    {{ number_format($row['billed'], 2) }}
                                </td>

                                <td class="text-right positive">
                                    QAR
                                    {{ number_format($row['collection'], 2) }}
                                </td>

                                <td class="text-right negative">
                                    QAR
                                    {{ number_format($row['expenses'], 2) }}
                                </td>

                                <td class="text-right bold {{ $row['profit'] >= 0 ? 'positive' : 'negative' }}">
                                    QAR
                                    {{ number_format($row['profit'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center muted">
                                    No financial records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h3 class="section-title">
                    Invoice Status Summary
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="text-right">Invoices</th>
                            <th class="text-right">Gross</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Due</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($invoiceStatuses as $row)
                            <tr>
                                <td>
                                    <span class="status status-{{ $row['status'] }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>

                                <td class="text-right">
                                    {{ $row['invoice_count'] }}
                                </td>

                                <td class="text-right">
                                    QAR
                                    {{ number_format($row['gross_amount'], 2) }}
                                </td>

                                <td class="text-right">
                                    QAR
                                    {{ number_format($row['discount_amount'], 2) }}
                                </td>

                                <td class="text-right positive">
                                    QAR
                                    {{ number_format($row['paid_amount'], 2) }}
                                </td>

                                <td class="text-right negative">
                                    QAR
                                    {{ number_format($row['due_amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center muted">
                                    No invoices found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        @if(
            $report === 'full'
            || $report === 'collections'
        )
            <section class="section {{ $report === 'full' ? 'new-page' : '' }}">
                <h3 class="section-title">
                    Collection by Payment Method
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th class="text-right">Transactions</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($paymentMethods as $row)
                            <tr>
                                <td>
                                    {{ $row['name'] }}
                                </td>

                                <td class="text-right">
                                    {{ $row['transaction_count'] }}
                                </td>

                                <td class="text-right positive bold">
                                    QAR
                                    {{ number_format($row['total'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center muted">
                                    No collections found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h3 class="section-title">
                    Collection Details
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 9%;">Date</th>
                            <th style="width: 19%;">Client</th>
                            <th style="width: 13%;">Invoice</th>
                            <th style="width: 15%;">Method</th>
                            <th>Transaction / Notes</th>
                            <th style="width: 13%;" class="text-right">
                                Amount
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($collections as $row)
                            <tr>
                                <td>
                                    {{ $row['date'] }}
                                </td>

                                <td>
                                    <strong>
                                        {{ $row['client_name'] ?: '-' }}
                                    </strong>

                                    <br>

                                    <span class="muted">
                                        {{ $row['client_code'] ?: '-' }}
                                    </span>
                                </td>

                                <td>
                                    {{ $row['invoice_no'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['method'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['transaction_id'] ?: $row['notes'] ?: '-' }}
                                </td>

                                <td class="text-right positive bold">
                                    QAR
                                    {{ number_format($row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center muted">
                                    No collection records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        @if(
            $report === 'full'
            || $report === 'expenses'
        )
            <section class="section {{ $report === 'full' ? 'new-page' : '' }}">
                <h3 class="section-title">
                    Expenses by Category
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-right">Transactions</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($expenseCategories as $row)
                            <tr>
                                <td>
                                    {{ $row['name'] }}
                                </td>

                                <td class="text-right">
                                    {{ $row['transaction_count'] }}
                                </td>

                                <td class="text-right negative bold">
                                    QAR
                                    {{ number_format($row['total'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center muted">
                                    No expenses found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h3 class="section-title">
                    Expense Details
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 9%;">Date</th>
                            <th style="width: 18%;">Category</th>
                            <th style="width: 22%;">Title</th>
                            <th style="width: 15%;">Method</th>
                            <th>Notes</th>
                            <th style="width: 13%;" class="text-right">
                                Amount
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($expenses as $row)
                            <tr>
                                <td>
                                    {{ $row['date'] }}
                                </td>

                                <td>
                                    {{ $row['category'] ?: '-' }}
                                </td>

                                <td class="bold">
                                    {{ $row['title'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['method'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['notes'] ?: '-' }}
                                </td>

                                <td class="text-right negative bold">
                                    QAR
                                    {{ number_format($row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center muted">
                                    No expense records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        @if(
            $report === 'full'
            || $report === 'receivables'
        )
            <section class="section {{ $report === 'full' ? 'new-page' : '' }}">
                <h3 class="section-title">
                    Customer Due / Accounts Receivable
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Client</th>
                            <th style="width: 15%;">Phone</th>
                            <th class="text-right">Invoices</th>
                            <th>Oldest Due Date</th>
                            <th>Expiry Date</th>
                            <th class="text-right">Total Due</th>
                            <th class="text-right">Overdue</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($receivables as $row)
                            <tr>
                                <td>
                                    <strong>
                                        {{ $row['client_name'] ?: '-' }}
                                    </strong>

                                    <br>

                                    <span class="muted">
                                        {{ $row['client_code'] ?: '-' }}
                                    </span>
                                </td>

                                <td>
                                    {{ $row['phone'] ?: '-' }}
                                </td>

                                <td class="text-right">
                                    {{ $row['invoice_count'] }}
                                </td>

                                <td>
                                    {{ $row['oldest_due_date'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['expiry_date'] ?: '-' }}
                                </td>

                                <td class="text-right negative bold">
                                    QAR
                                    {{ number_format($row['total_due'], 2) }}
                                </td>

                                <td class="text-right negative bold">
                                    QAR
                                    {{ number_format($row['overdue_due'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center muted">
                                    No customer due found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        @if(
            $canViewClients
            && (
                $report === 'full'
                || $report === 'clients'
            )
        )
            <section class="section {{ $report === 'full' ? 'new-page' : '' }}">
                <h3 class="section-title">
                    All Client Details List
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">
                                Client
                            </th>

                            <th style="width: 11%;">
                                Contact
                            </th>

                            <th style="width: 13%;">
                                Network
                            </th>

                            <th style="width: 10%;">
                                Router / Pool
                            </th>

                            <th style="width: 10%;">
                                Package
                            </th>

                            <th style="width: 8%;">
                                Installed
                            </th>

                            <th style="width: 8%;">
                                Expiry
                            </th>

                            <th style="width: 7%;">
                                Status
                            </th>

                            <th style="width: 7%;" class="text-right">
                                Activity
                            </th>

                            <th style="width: 8%;" class="text-right">
                                Paid
                            </th>

                            <th style="width: 8%;" class="text-right">
                                Due
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($clients as $row)
                            <tr>
                                <td>
                                    <strong>
                                        {{ $row['name'] ?: '-' }}
                                    </strong>

                                    <br>

                                    <span class="muted">
                                        {{ $row['client_code'] ?: '-' }}
                                    </span>

                                    <br>

                                    <span class="muted">
                                        {{ $row['address'] ?: '-' }}
                                    </span>
                                </td>

                                <td>
                                    {{ $row['phone'] ?: '-' }}

                                    <br>

                                    <span class="muted">
                                        {{ $row['email'] ?: '-' }}
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        IP:
                                        {{ $row['ip_address'] ?: '-' }}
                                    </strong>

                                    <br>

                                    MAC:
                                    {{ $row['mac_address'] ?: '-' }}
                                </td>

                                <td>
                                    Router:
                                    {{ $row['router_name'] ?: '-' }}

                                    <br>

                                    <span class="muted">
                                        {{ $row['router_host'] ?: '-' }}
                                    </span>

                                    <br>

                                    Pool:
                                    {{ $row['ip_pool_name'] ?: '-' }}
                                </td>

                                <td>
                                    <strong>
                                        {{ $row['package_name'] ?: '-' }}
                                    </strong>

                                    <br>

                                    QAR
                                    {{ number_format($row['package_price'], 2) }}

                                    <br>

                                    {{ $row['package_validity_days'] }} days

                                    <br>

                                    <span class="muted">
                                        {{ $row['speed_download'] ?: '-' }}
                                        down /
                                        {{ $row['speed_upload'] ?: '-' }}
                                        up
                                    </span>
                                </td>

                                <td>
                                    {{ $row['installed_at'] ?: '-' }}

                                    <br>

                                    <span class="muted">
                                        Bill day:
                                        {{ $row['billing_day'] ?: '-' }}
                                    </span>
                                </td>

                                <td>
                                    {{ $row['expiry_date'] ?: '-' }}
                                </td>

                                <td>
                                    <strong class="{{ $row['enabled'] ? 'positive' : 'negative' }}">
                                        {{ $row['enabled'] ? 'Active' : 'Suspended' }}
                                    </strong>

                                    <br>

                                    <span class="{{ $row['connected'] ? 'positive' : 'muted' }}">
                                        {{ $row['connected'] ? 'Online' : 'Offline' }}
                                    </span>
                                </td>

                                <td class="text-right">
                                    Invoices:
                                    {{ $row['invoice_count'] }}

                                    <br>

                                    Payments:
                                    {{ $row['payment_count'] }}
                                </td>

                                <td class="text-right positive bold">
                                    QAR
                                    {{ number_format($row['total_paid'], 2) }}
                                </td>

                                <td class="text-right negative bold">
                                    QAR
                                    {{ number_format($row['total_due'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="11"
                                    class="text-center muted"
                                >
                                    No clients found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        @if(
            $report === 'full'
            || $report === 'transactions'
        )
            <section class="section {{ $report === 'full' ? 'new-page' : '' }}">
                <h3 class="section-title">
                    Cash Flow and Transactions
                </h3>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 9%;">Date</th>
                            <th style="width: 10%;">Type</th>
                            <th>Description</th>
                            <th style="width: 15%;">Category</th>
                            <th style="width: 14%;">Reference</th>
                            <th style="width: 11%;" class="text-right">
                                Money In
                            </th>
                            <th style="width: 11%;" class="text-right">
                                Money Out
                            </th>
                            <th style="width: 12%;" class="text-right">
                                Balance
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($transactions as $row)
                            <tr>
                                <td>
                                    {{ $row['date'] }}
                                </td>

                                <td>
                                    {{ $row['type'] === 'collection' ? 'Money In' : 'Money Out' }}
                                </td>

                                <td>
                                    {{ $row['description'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['category'] ?: '-' }}
                                </td>

                                <td>
                                    {{ $row['reference'] ?: '-' }}
                                </td>

                                <td class="text-right positive">
                                    QAR
                                    {{ number_format($row['money_in'], 2) }}
                                </td>

                                <td class="text-right negative">
                                    QAR
                                    {{ number_format($row['money_out'], 2) }}
                                </td>

                                <td class="text-right bold">
                                    QAR
                                    {{ number_format($row['balance'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center muted">
                                    No cash-flow transactions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        <footer class="footer">
            <table class="footer-table">
                <tr>
                    <td>
                        This is a cash-basis operational accounting report generated from MikroPanel billing records.
                    </td>

                    <td>
                        {{ $company['name'] }}
                    </td>
                </tr>
            </table>
        </footer>
    </main>
</body>
</html>
