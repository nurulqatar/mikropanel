<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Reseller;
use App\Models\ResellerRecharge;
use App\Models\ResellerSubscription;
use App\Services\Reseller\ResellerUsageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResellerReportController extends Controller
{
    public function index(
        Request $request,
        ResellerUsageService $usage
    ): Response {
        $this->access(
            $request
        );

        return Inertia::render(
            'SuperAdmin/Reports/Index',
            $this->data(
                $usage
            )
        );
    }

    public function csv(
        Request $request,
        ResellerUsageService $usage
    ): StreamedResponse {
        $this->access(
            $request
        );

        $data =
            $this->data(
                $usage
            );

        return response()
            ->streamDownload(
                function () use (
                    $data
                ): void {
                    $out =
                        fopen(
                            'php://output',
                            'w'
                        );

                    fputcsv(
                        $out,
                        [
                            'Code',
                            'Company',
                            'Status',
                            'Plan',
                            'Client Limit',
                            'Used Clients',
                            'Remaining',
                            'Routers',
                            'Operators',
                            'Wallet',
                            'Expiry',
                        ]
                    );

                    foreach (
                        $data['rows']
                        as $row
                    ) {
                        fputcsv(
                            $out,
                            [
                                $row['code'],
                                $row[
                                    'company_name'
                                ],
                                $row['status'],
                                $row['plan'],
                                $row[
                                    'client_limit'
                                ],
                                $row[
                                    'used_clients'
                                ],
                                $row[
                                    'remaining_clients'
                                ],
                                $row[
                                    'routers'
                                ],
                                $row[
                                    'operators'
                                ],
                                $row[
                                    'wallet_balance'
                                ],
                                $row[
                                    'expires_at'
                                ],
                            ]
                        );
                    }

                    fclose($out);
                },
                'reseller-report-'
                    . now()
                        ->format(
                            'Y-m-d'
                        )
                    . '.csv',
                [
                    'Content-Type' =>
                        'text/csv',
                ]
            );
    }

    private function data(
        ResellerUsageService $usage
    ): array {
        $now =
            Carbon::now(
                'Asia/Qatar'
            );

        $resellers =
            Reseller::query()
                ->with(
                    'activeSubscription.plan'
                )
                ->orderBy(
                    'company_name'
                )
                ->get();

        $rows =
            $resellers
                ->map(
                    function (
                        Reseller $reseller
                    ) use (
                        $usage
                    ): array {
                        $snapshot =
                            $usage->snapshot(
                                $reseller
                            );

                        return [
                            'id' =>
                                $reseller->id,

                            'code' =>
                                $reseller->code,

                            'company_name' =>
                                $reseller
                                    ->company_name,

                            'status' =>
                                $reseller
                                    ->status,

                            'plan' =>
                                $reseller
                                    ->activeSubscription
                                    ?->plan
                                    ?->name
                                ?? '-',

                            'client_limit' =>
                                $snapshot[
                                    'client_limit'
                                ],

                            'used_clients' =>
                                $snapshot[
                                    'used_clients'
                                ],

                            'remaining_clients' =>
                                $snapshot[
                                    'remaining_clients'
                                ],

                            'routers' =>
                                $snapshot[
                                    'routers'
                                ],

                            'operators' =>
                                $snapshot[
                                    'operators'
                                ],

                            'wallet_balance' =>
                                $snapshot[
                                    'wallet_balance'
                                ],

                            'expires_at' =>
                                $snapshot[
                                    'subscription_expires_at'
                                ]
                                ?? null,
                        ];
                    }
                )
                ->values();

        return [
            'summary' => [
                'resellers' =>
                    $resellers
                        ->count(),

                'active' =>
                    $resellers
                        ->where(
                            'status',
                            'active'
                        )
                        ->count(),

                'suspended' =>
                    $resellers
                        ->where(
                            'status',
                            'suspended'
                        )
                        ->count(),

                'total_slots' =>
                    $rows
                        ->sum(
                            'client_limit'
                        ),

                'used_slots' =>
                    $rows
                        ->sum(
                            'used_clients'
                        ),

                'wallet_balance' =>
                    round(
                        (float)
                        $resellers
                            ->sum(
                                'wallet_balance'
                            ),
                        2
                    ),

                'month_recharge' =>
                    round(
                        (float)
                        ResellerRecharge::query()
                            ->where(
                                'status',
                                'approved'
                            )
                            ->whereBetween(
                                'approved_at',
                                [
                                    $now
                                        ->copy()
                                        ->startOfMonth(),

                                    $now
                                        ->copy()
                                        ->endOfMonth(),
                                ]
                            )
                            ->sum(
                                'amount'
                            ),
                        2
                    ),

                'expired' =>
                    ResellerSubscription::query()
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'expires_at',
                            '<',
                            $now
                        )
                        ->count(),

                'tenant_clients' =>
                    Client::query()
                        ->whereNotNull(
                            'reseller_id'
                        )
                        ->count(),
            ],

            'rows' =>
                $rows,
        ];
    }

    private function access(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->isSuperAdmin(),
            403
        );
    }
}
