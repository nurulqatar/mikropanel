<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_no }}</title>
    <style>
        body{margin:0;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif}.page{width:210mm;min-height:297mm;margin:16px auto;background:white;box-sizing:border-box;padding:18mm}.top{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #0f172a;padding-bottom:18px}.title{font-size:28px;font-weight:800}.muted{color:#64748b}table{width:100%;border-collapse:collapse;margin-top:24px}th,td{text-align:left;padding:11px;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;font-size:12px;text-transform:uppercase}.totals{width:360px;margin-left:auto;margin-top:24px}.totals div{display:flex;justify-content:space-between;padding:7px 0}.due{font-size:20px;font-weight:800}.actions{width:210mm;margin:16px auto}button{padding:10px 18px;font-weight:700;cursor:pointer}@media print{body{background:white}.page{margin:0;width:auto;min-height:auto}.actions{display:none}}
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print Invoice</button></div>
<main class="page">
    <div class="top">
        <div><div class="title">Rental Invoice</div><div class="muted">MikroPanel Commercial Services</div></div>
        <div><strong>{{ $invoice->invoice_no }}</strong><div class="muted">Issue: {{ $invoice->issue_date }}</div><div class="muted">Due: {{ $invoice->due_date }}</div></div>
    </div>
    <div style="margin-top:24px"><strong>Bill To</strong><div style="margin-top:8px">{{ $invoice->customer_name }}</div>@if($invoice->customer_email)<div class="muted">{{ $invoice->customer_email }}</div>@endif</div>
    <table><thead><tr><th>Service</th><th>Plan</th><th>Period</th><th>Amount</th></tr></thead><tbody><tr><td>{{ ucfirst($invoice->service_type) }}</td><td>{{ $invoice->plan_name ?: '-' }}</td><td>{{ $invoice->period_start ?: '-' }} - {{ $invoice->period_end ?: '-' }}</td><td>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td></tr></tbody></table>
    <div class="totals">
        <div><span>Amount</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</strong></div>
        <div><span>Discount</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->discount, 2) }}</strong></div>
        <div><span>Paid</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</strong></div>
        <div class="due"><span>Due</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->due_amount, 2) }}</span></div>
    </div>
    @if($invoice->notes)<div style="margin-top:28px"><strong>Notes</strong><p class="muted">{{ $invoice->notes }}</p></div>@endif
    @if($payments->count())
        <div style="margin-top:28px"><strong>Payments</strong><table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>{{ $payment->payment_date }}</td><td>{{ $payment->payment_method }}</td><td>{{ $payment->reference ?: '-' }}</td><td>{{ $invoice->currency }} {{ number_format((float) $payment->amount, 2) }}</td></tr>@endforeach</tbody></table></div>
    @endif
</main>
</body>
</html>
