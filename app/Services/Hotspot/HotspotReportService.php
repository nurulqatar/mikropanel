<?php

namespace App\Services\Hotspot;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HotspotReportService
{
    public function report(
        ?string $from = null,
        ?string $to = null
    ): array {
        $now = Carbon::now(
            'Asia/Qatar'
        );

        $from =
            $from
            ?: $now
                ->copy()
                ->startOfMonth()
                ->toDateString();

        $to =
            $to
            ?: $now
                ->toDateString();

        $paymentQuery =
            DB::table(
                'hotspot_payments'
            )
                ->whereDate(
                    'payment_date',
                    '>=',
                    $from
                )
                ->whereDate(
                    'payment_date',
                    '<=',
                    $to
                );

        $invoiceQuery =
            DB::table(
                'hotspot_invoices'
            )
                ->where(
                    'status',
                    '!=',
                    'cancelled'
                )
                ->whereDate(
                    'issue_date',
                    '>=',
                    $from
                )
                ->whereDate(
                    'issue_date',
                    '<=',
                    $to
                );

        $collection =
            round(
                (float)
                (clone $paymentQuery)
                    ->sum('amount'),
                2
            );

        $billed =
            round(
                (float)
                (clone $invoiceQuery)
                    ->sum('amount'),
                2
            );

        $periodDue =
            round(
                (float)
                (clone $invoiceQuery)
                    ->sum(
                        'due_amount'
                    ),
                2
            );

        $allDue =
            round(
                (float)
                DB::table(
                    'hotspot_invoices'
                )
                    ->where(
                        'status',
                        '!=',
                        'cancelled'
                    )
                    ->sum(
                        'due_amount'
                    ),
                2
            );

        $payments =
            DB::table(
                'hotspot_payments as hp'
            )
                ->leftJoin(
                    'hotspot_vouchers as hv',
                    'hv.id',
                    '=',
                    'hp.hotspot_voucher_id'
                )
                ->leftJoin(
                    'hotspot_invoices as hi',
                    'hi.id',
                    '=',
                    'hp.hotspot_invoice_id'
                )
                ->leftJoin(
                    'users as u',
                    'u.id',
                    '=',
                    'hp.received_by'
                )
                ->whereDate(
                    'hp.payment_date',
                    '>=',
                    $from
                )
                ->whereDate(
                    'hp.payment_date',
                    '<=',
                    $to
                )
                ->orderByDesc(
                    'hp.payment_date'
                )
                ->orderByDesc(
                    'hp.id'
                )
                ->limit(500)
                ->get([
                    'hp.id',
                    'hp.payment_date',
                    'hp.amount',
                    'hp.payment_method',
                    'hp.transaction_id',
                    'hv.username',
                    'hi.invoice_no',
                    'u.name as received_by_name',
                ]);

        $operators =
            DB::table(
                'hotspot_payments as hp'
            )
                ->leftJoin(
                    'users as u',
                    'u.id',
                    '=',
                    'hp.received_by'
                )
                ->whereDate(
                    'hp.payment_date',
                    '>=',
                    $from
                )
                ->whereDate(
                    'hp.payment_date',
                    '<=',
                    $to
                )
                ->groupBy(
                    'hp.received_by',
                    'u.name'
                )
                ->orderByDesc(
                    DB::raw(
                        'SUM(hp.amount)'
                    )
                )
                ->get([
                    'hp.received_by',
                    'u.name',
                    DB::raw(
                        'COUNT(*) as transactions'
                    ),
                    DB::raw(
                        'SUM(hp.amount) as total'
                    ),
                ]);

        return [
            'from' =>
                $from,

            'to' =>
                $to,

            'summary' => [
                'collection' =>
                    $collection,

                'billed' =>
                    $billed,

                'period_due' =>
                    $periodDue,

                'all_due' =>
                    $allDue,

                'sold_vouchers' =>
                    DB::table(
                        'hotspot_vouchers'
                    )
                        ->whereNotNull(
                            'sold_at'
                        )
                        ->whereDate(
                            'sold_at',
                            '>=',
                            $from
                        )
                        ->whereDate(
                            'sold_at',
                            '<=',
                            $to
                        )
                        ->count(),

                'active_vouchers' =>
                    DB::table(
                        'hotspot_vouchers'
                    )
                        ->whereNull(
                            'deleted_at'
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->count(),

                'online_sessions' =>
                    DB::table(
                        'hotspot_sessions'
                    )
                        ->where(
                            'active',
                            1
                        )
                        ->count(),
            ],

            'payments' =>
                $payments,

            'operators' =>
                $operators,
        ];
    }
}
