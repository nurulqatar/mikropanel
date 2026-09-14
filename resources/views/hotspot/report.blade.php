<!doctype html>
<html>
<head>
    <meta charset="utf-8">

    <title>Hotspot Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
        }

        h1 {
            margin-bottom: 3px;
        }

        .period {
            color: #64748b;
            margin-bottom: 18px;
        }

        .summary {
            width: 100%;
            margin-bottom: 18px;
        }

        .summary td {
            border: 1px solid #cbd5e1;
            padding: 8px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            text-align: left;
        }

        table.data th {
            background: #f1f5f9;
        }
    </style>
</head>

<body>
    <h1>Hotspot Financial Report</h1>

    <div class="period">
        {{ $report['from'] }}
        to
        {{ $report['to'] }}
    </div>

    <table class="summary">
        <tr>
            <td>
                Collection:
                <strong>
                    QAR
                    {{ number_format(
                        $report['summary']['collection'],
                        2
                    ) }}
                </strong>
            </td>

            <td>
                Billed:
                <strong>
                    QAR
                    {{ number_format(
                        $report['summary']['billed'],
                        2
                    ) }}
                </strong>
            </td>

            <td>
                Period Due:
                <strong>
                    QAR
                    {{ number_format(
                        $report['summary']['period_due'],
                        2
                    ) }}
                </strong>
            </td>

            <td>
                All Due:
                <strong>
                    QAR
                    {{ number_format(
                        $report['summary']['all_due'],
                        2
                    ) }}
                </strong>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Voucher</th>
                <th>Invoice</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Transaction</th>
                <th>Operator</th>
            </tr>
        </thead>

        <tbody>
            @forelse(
                $report['payments']
                as $row
            )
                <tr>
                    <td>
                        {{ $row->payment_date }}
                    </td>

                    <td>
                        {{ $row->username }}
                    </td>

                    <td>
                        {{ $row->invoice_no }}
                    </td>

                    <td>
                        QAR
                        {{ number_format(
                            (float) $row->amount,
                            2
                        ) }}
                    </td>

                    <td>
                        {{ $row->payment_method }}
                    </td>

                    <td>
                        {{ $row->transaction_id }}
                    </td>

                    <td>
                        {{ $row->received_by_name }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        No payment in selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
