<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UnifiedFinanceService
{
    public function summary(): array
    {
        $now = Carbon::now(
            'Asia/Qatar'
        );

        $today =
            $now->toDateString();

        $monthStart =
            $now
                ->copy()
                ->startOfMonth()
                ->toDateString();

        $monthEnd =
            $now
                ->copy()
                ->endOfMonth()
                ->toDateString();

        $normalReceived =
            $this->paymentSum(
                'payments'
            );

        $hotspotReceived =
            $this->paymentSum(
                'hotspot_payments'
            );

        $normalToday =
            $this->paymentSum(
                'payments',
                $today,
                $today
            );

        $hotspotToday =
            $this->paymentSum(
                'hotspot_payments',
                $today,
                $today
            );

        $normalMonth =
            $this->paymentSum(
                'payments',
                $monthStart,
                $monthEnd
            );

        $hotspotMonth =
            $this->paymentSum(
                'hotspot_payments',
                $monthStart,
                $monthEnd
            );

        $normalDue =
            round(
                (float)
                DB::table('invoices')
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

        $hotspotDue =
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

        return [
            'normal_received' =>
                $normalReceived,

            'hotspot_received' =>
                $hotspotReceived,

            'combined_received' =>
                round(
                    $normalReceived
                    + $hotspotReceived,
                    2
                ),

            'normal_today' =>
                $normalToday,

            'hotspot_today' =>
                $hotspotToday,

            'combined_today' =>
                round(
                    $normalToday
                    + $hotspotToday,
                    2
                ),

            'normal_month' =>
                $normalMonth,

            'hotspot_month' =>
                $hotspotMonth,

            'combined_month' =>
                round(
                    $normalMonth
                    + $hotspotMonth,
                    2
                ),

            'normal_due' =>
                $normalDue,

            'hotspot_due' =>
                $hotspotDue,

            'combined_due' =>
                round(
                    $normalDue
                    + $hotspotDue,
                    2
                ),
        ];
    }

    private function paymentSum(
        string $table,
        ?string $from = null,
        ?string $to = null
    ): float {
        $query =
            DB::table($table);

        if ($from !== null) {
            $query->whereDate(
                'payment_date',
                '>=',
                $from
            );
        }

        if ($to !== null) {
            $query->whereDate(
                'payment_date',
                '<=',
                $to
            );
        }

        return round(
            (float)
            $query->sum('amount'),
            2
        );
    }
}
