@php
    $showCustomClientReport =
        ($canViewClients ?? false)
        && in_array(
            $report ?? 'full',
            [
                'full',
                'clients',
                'collections',
                'receivables',
            ],
            true
        );

    $customReportFields = collect();
    $customReportClients = collect();
    $customReportValueMatrix = [];

    if ($showCustomClientReport) {
        $customReportFields =
            \App\Models\ClientCustomField::query()
                ->where('is_enabled', true)
                ->where('show_in_reports', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        if ($customReportFields->isNotEmpty()) {
            $customReportClients =
                \App\Models\Client::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'client_code',
                        'name',
                    ]);

            $customReportValueMatrix =
                app(
                    \App\Services\ClientCustomFieldService::class
                )->valuesForClients(
                    $customReportClients
                        ->pluck('id')
                        ->all(),
                    $customReportFields
                        ->pluck('id')
                        ->all()
                );
        }
    }
@endphp

@if (
    $showCustomClientReport
    && $customReportFields->isNotEmpty()
)
    <section class="section new-page">
        <h3>
            Additional Client Information
        </h3>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 24%;">
                        Client
                    </th>

                    <th>
                        Custom Information
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse (
                    $customReportClients
                    as $customClient
                )
                    @php
                        $customValues =
                            $customReportValueMatrix[
                                (string)
                                $customClient->id
                            ] ?? [];
                    @endphp

                    <tr>
                        <td>
                            <strong>
                                {{ $customClient->name }}
                            </strong>

                            <br>

                            <span
                                style="
                                    font-size: 9px;
                                    color: #64748b;
                                "
                            >
                                {{ $customClient->client_code }}
                            </span>
                        </td>

                        <td>
                            @foreach (
                                $customReportFields
                                as $customField
                            )
                                @php
                                    $customRawValue =
                                        $customValues[
                                            (string)
                                            $customField->id
                                        ] ?? null;

                                    $customDisplayValue =
                                        app(
                                            \App\Services\ClientCustomFieldService::class
                                        )->displayValue(
                                            $customField,
                                            $customRawValue
                                        );
                                @endphp

                                <div
                                    style="
                                        display: inline-block;
                                        margin: 0 10px 4px 0;
                                    "
                                >
                                    <strong>
                                        {{ $customField->name }}:
                                    </strong>

                                    {{ $customDisplayValue }}
                                </div>
                            @endforeach
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="2">
                            No clients found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endif
