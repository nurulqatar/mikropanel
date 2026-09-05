<?php

namespace App\Http\Controllers;

use App\Services\Hotspot\HotspotReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HotspotReportController extends Controller
{
    public function index(
        Request $request,
        HotspotReportService $reports
    ): Response {
        $this->access($request);

        $data =
            $request->validate([
                'from' => [
                    'nullable',
                    'date',
                ],

                'to' => [
                    'nullable',
                    'date',
                    'after_or_equal:from',
                ],
            ]);

        return Inertia::render(
            'Hotspot/Reports',
            [
                'report' =>
                    $reports->report(
                        $data['from']
                            ?? null,

                        $data['to']
                            ?? null
                    ),
            ]
        );
    }

    public function csv(
        Request $request,
        HotspotReportService $reports
    ): StreamedResponse {
        $this->access($request);

        $report =
            $reports->report(
                $request->query(
                    'from'
                ),
                $request->query(
                    'to'
                )
            );

        $filename =
            'hotspot-report-'
            . $report['from']
            . '-'
            . $report['to']
            . '.csv';

        return response()
            ->streamDownload(
                function () use (
                    $report
                ): void {
                    $out =
                        fopen(
                            'php://output',
                            'w'
                        );

                    fputcsv(
                        $out,
                        [
                            'Date',
                            'Voucher',
                            'Invoice',
                            'Amount',
                            'Method',
                            'Transaction',
                            'Received By',
                        ]
                    );

                    foreach (
                        $report['payments']
                        as $row
                    ) {
                        fputcsv(
                            $out,
                            [
                                $row
                                    ->payment_date,

                                $row
                                    ->username,

                                $row
                                    ->invoice_no,

                                $row
                                    ->amount,

                                $row
                                    ->payment_method,

                                $row
                                    ->transaction_id,

                                $row
                                    ->received_by_name,
                            ]
                        );
                    }

                    fclose($out);
                },
                $filename,
                [
                    'Content-Type' =>
                        'text/csv',
                ]
            );
    }

    public function pdf(
        Request $request,
        HotspotReportService $reports
    ) {
        $this->access($request);

        $report =
            $reports->report(
                $request->query(
                    'from'
                ),
                $request->query(
                    'to'
                )
            );

        return Pdf::loadView(
            'hotspot.report',
            [
                'report' =>
                    $report,
            ]
        )
            ->setPaper(
                'a4',
                'landscape'
            )
            ->download(
                'hotspot-report-'
                . $report['from']
                . '-'
                . $report['to']
                . '.pdf'
            );
    }

    private function access(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->hasAnyPermission([
                    'hotspot.view',
                    'hotspot.export',
                ]),
            403
        );
    }
}
