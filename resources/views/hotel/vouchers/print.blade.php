<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        WiFi Voucher - {{ $voucher->username }}
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .card {
            width: 360px;
            max-width: 100%;
            margin: 0 auto;
            padding: 28px;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 15px 45px rgba(15, 23, 42, .12);
            text-align: center;
        }

        .logo {
            max-width: 180px;
            max-height: 80px;
            margin-bottom: 16px;
        }

        .hotel {
            font-size: 22px;
            font-weight: 800;
        }

        .subtitle {
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .code {
            margin: 24px 0;
            padding: 22px 12px;
            border-radius: 16px;
            background: #0f172a;
            color: white;
            font-family: monospace;
            font-weight: 900;
            font-size: 30px;
            letter-spacing: 5px;
        }

        .row {
            margin: 9px 0;
            font-size: 14px;
        }

        .footer {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
            color: #64748b;
            font-size: 11px;
        }

        .actions {
            margin: 20px auto;
            text-align: center;
        }

        button {
            border: 0;
            border-radius: 10px;
            padding: 10px 22px;
            background: #0f766e;
            color: white;
            font-weight: 800;
            cursor: pointer;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .card {
                box-shadow: none;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="actions">
        <button onclick="window.print()">
            Print Voucher
        </button>
    </div>

    <div class="card">
        @if($voucher->hotel->logo_path)
            <img
                class="logo"
                src="{{ asset('storage/'.$voucher->hotel->logo_path) }}"
                alt="{{ $voucher->hotel->name }}"
            >
        @endif

        <div class="hotel">
            {{ $voucher->hotel->name }}
        </div>

        <div class="subtitle">
            Guest WiFi Access Voucher
        </div>

        <div class="code">
            {{ $voucher->username }}
        </div>

        <div class="row">
            <strong>Guest:</strong>
            {{ $voucher->stay->guest->name }}
        </div>

        <div class="row">
            <strong>Room:</strong>
            {{ $voucher->stay->room_number }}
        </div>

        <div class="row">
            <strong>Check-in:</strong>
            {{
                $voucher
                    ->stay
                    ->check_in_at
                    ->timezone(
                        $voucher
                            ->hotel
                            ->timezone
                    )
                    ->format('Y-m-d')
            }}
        </div>

        <div class="row">
            <strong>Valid Until:</strong>
            {{
                $voucher
                    ->expires_at
                    ->timezone(
                        $voucher
                            ->hotel
                            ->timezone
                    )
                    ->format('Y-m-d H:i')
            }}
        </div>

        <div class="footer">
            Keep this voucher private.
            It is valid only until the guest checkout time.

            @if($voucher->hotel->phone)
                <br>
                {{ $voucher->hotel->phone }}
            @endif
        </div>
    </div>
</body>
</html>
