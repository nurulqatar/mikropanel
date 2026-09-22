<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <title>
        {{ $title ?? 'Hotspot Vouchers' }}
    </title>

    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #111827;
            font-family:
                DejaVu Sans,
                Arial,
                sans-serif;
        }

        .voucher-page {
            width: 100%;
            page-break-after: always;
        }

        .voucher-page:last-child {
            page-break-after: auto;
        }

        .voucher-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 1.2mm 1.2mm;
            table-layout: fixed;
        }

        .voucher-cell {
            width: 25%;
            height: 33mm;
            padding: 0;
            vertical-align: top;
        }

        .voucher-card {
            height: 31.8mm;
            overflow: hidden;
            border: 0.35mm solid #111827;
            border-radius: 1.4mm;
            padding: 1.7mm 1.7mm 1.4mm;
            text-align: center;
        }

        .company {
            height: 5.3mm;
            overflow: hidden;
            font-size: 7.8pt;
            line-height: 8.5pt;
            font-weight: 700;
        }

        .code-label {
            margin-top: 0.3mm;
            color: #4b5563;
            font-size: 5.3pt;
            line-height: 6pt;
            letter-spacing: 0.15mm;
            text-transform: uppercase;
        }

        .code {
            margin-top: 0.1mm;
            font-size: 15.5pt;
            line-height: 16.5pt;
            font-weight: 800;
            letter-spacing: 0.55mm;
        }

        .price {
            margin-top: 0.3mm;
            font-size: 7pt;
            line-height: 7.7pt;
            font-weight: 700;
        }

        .validity {
            margin-top: 0.2mm;
            font-size: 6.7pt;
            line-height: 7.5pt;
            font-weight: 700;
        }

        .policy {
            margin-top: 0.5mm;
            color: #374151;
            font-size: 5.1pt;
            line-height: 5.9pt;
        }

        .portal {
            margin-top: 0.5mm;
            overflow: hidden;
            font-size: 5.9pt;
            line-height: 6.7pt;
            font-weight: 700;
            word-break: break-all;
        }

        .empty-card {
            border-color: transparent;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

@foreach(
    array_chunk(
        $items,
        32
    )
    as $pageItems
)
    <div class="voucher-page">

        <table class="voucher-grid">

            @foreach(
                array_chunk(
                    $pageItems,
                    4
                )
                as $rowItems
            )
                @php
                    $rowItems =
                        array_pad(
                            $rowItems,
                            4,
                            null
                        );
                @endphp

                <tr>

                    @foreach(
                        $rowItems
                        as $item
                    )
                        <td class="voucher-cell">

                            @if($item)

                                <div class="voucher-card">

                                    <div class="company">
                                        {{
                                            $item[
                                                'company_name'
                                            ]
                                        }}
                                    </div>

                                    <div class="code-label">
                                        Voucher Code
                                    </div>

                                    <div class="code">
                                        {{
                                            $item[
                                                'username'
                                            ]
                                        }}
                                    </div>

                                    <div class="price">
                                        Price:
                                        {{
                                            $item[
                                                'currency'
                                            ]
                                        }}
                                        {{
                                            number_format(
                                                (float) $item[
                                                    'price'
                                                ],
                                                2
                                            )
                                        }}
                                    </div>

                                    <div class="validity">
                                        Validity:
                                        {{
                                            $item[
                                                'validity'
                                            ]
                                        }}
                                    </div>

                                    <div class="policy">
                                        Validity starts according to the voucher service policy.
                                    </div>

                                    <div class="portal">
                                        Portal:
                                        {{
                                            $item[
                                                'dns_name'
                                            ]
                                        }}
                                    </div>

                                </div>

                            @else

                                <div class="voucher-card empty-card">
                                    &nbsp;
                                </div>

                            @endif

                        </td>
                    @endforeach

                </tr>

            @endforeach

        </table>

    </div>
@endforeach

@if($autoPrint ?? false)
<script>
window.addEventListener(
    'load',
    function () {
        window.print();
    }
);
</script>
@endif

</body>
</html>
