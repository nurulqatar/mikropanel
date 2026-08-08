@php
    $customInvoiceRows = [];

    if (isset($client) && $client) {
        $customInvoiceRows = app(
            \App\Services\ClientCustomFieldService::class
        )->rowsForClient(
            $client,
            'invoice'
        );
    }
@endphp

@if (!empty($customInvoiceRows))
    <div
        style="
            margin-top: 10px;
            padding: 8px 10px;
            border: 1px solid #dbe3ea;
            border-radius: 6px;
            background: #f8fafc;
        "
    >
        <div
            style="
                margin-bottom: 5px;
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                color: #64748b;
            "
        >
            Additional Client Information
        </div>

        <table
            style="
                width: 100%;
                border-collapse: collapse;
                font-size: 9px;
            "
        >
            @foreach ($customInvoiceRows as $customRow)
                <tr>
                    <td
                        style="
                            width: 42%;
                            padding: 2px 4px 2px 0;
                            color: #64748b;
                            vertical-align: top;
                        "
                    >
                        {{ $customRow['name'] }}
                    </td>

                    <td
                        style="
                            padding: 2px 0;
                            font-weight: 600;
                            color: #0f172a;
                            word-break: break-word;
                        "
                    >
                        {{ $customRow['display_value'] }}
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
