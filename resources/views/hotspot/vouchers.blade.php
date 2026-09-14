<!doctype html>
<html>
<head>
    <meta charset="utf-8">

    <title>{{ $title }}</title>

    <style>
        @page {
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                DejaVu Sans,
                Arial,
                sans-serif;
            color: #0f172a;
            background: #ffffff;
        }

        .toolbar {
            margin-bottom: 16px;
            text-align: center;
        }

        .toolbar button {
            border: 0;
            border-radius: 7px;
            padding: 9px 18px;
            background: #0891b2;
            color: #ffffff;
            font-weight: bold;
            cursor: pointer;
        }

        .grid {
            width: 100%;
        }

        .card {
            display: inline-block;
            width: 48%;
            min-height: 205px;
            margin: 0 1% 10px 0;
            padding: 12px;
            border: 1.5px dashed #64748b;
            border-radius: 10px;
            vertical-align: top;
            page-break-inside: avoid;
        }

        .brand {
            font-size: 16px;
            font-weight: bold;
            color: {{ $branding['primary_color'] ?? '#0891b2' }};
        }

        .plan {
            margin-top: 3px;
            font-size: 12px;
            color: #475569;
        }

        .content {
            margin-top: 10px;
            width: 100%;
        }

        .qr {
            width: 31%;
            float: right;
            text-align: center;
        }

        .qr svg {
            width: 100px;
            height: 100px;
        }

        .details {
            width: 66%;
            float: left;
        }

        .label {
            margin-top: 5px;
            font-size: 10px;
            color: #64748b;
        }

        .value {
            font-size: 15px;
            font-weight: bold;
            word-break: break-all;
        }

        .footer {
            clear: both;
            padding-top: 10px;
            font-size: 9px;
            color: #64748b;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>

@if($autoPrint)
    <div class="toolbar">
        <button onclick="window.print()">
            Print Vouchers
        </button>
    </div>
@endif

<div class="grid">
    @foreach($items as $item)
        <div class="card">
            <div class="brand">
                {{ $branding['brand_name'] ?? 'MikroPanel Hotspot' }}
            </div>

            <div class="plan">
                {{ $item['plan'] ?? '-' }}
                @if($branding['show_price'] ?? true)
                    · QAR
                    {{ number_format(
                        (float) ($item['price'] ?? 0),
                        2
                    ) }}
                @endif
                · {{ $item['validity'] }}
            </div>

            <div class="content">
                <div class="details">
                    <div class="label">
                        USERNAME
                    </div>

                    <div class="value">
                        {{ $item['username'] }}
                    </div>

                    <div class="label">
                        PASSWORD
                    </div>

                    <div class="value">
                        {{ $item['password'] }}
                    </div>

                    <div class="label">
                        SERVER
                    </div>

                    <div class="value">
                        {{ $item['server'] ?? '-' }}
                    </div>

                    @if(!empty($item['rate_limit']))
                        <div class="label">
                            SPEED
                        </div>

                        <div class="value">
                            {{ $item['rate_limit'] }}
                        </div>
                    @endif
                </div>

                @if($branding['show_qr'] ?? true)
                    <div class="qr">
                        {!! $item['qr_svg'] !!}
                    </div>
                @endif
            </div>

            <div class="footer">
                Validity starts according to the
                voucher service policy.
                @if(!empty($item['dns_name']))
                    Portal:
                    {{ $item['dns_name'] }}
                @endif
            </div>
        </div>
    @endforeach
</div>

@if($autoPrint)
<script>
    window.addEventListener(
        'load',
        () => {
            setTimeout(
                () => window.print(),
                350
            );
        }
    );
</script>
@endif

</body>
</html>
