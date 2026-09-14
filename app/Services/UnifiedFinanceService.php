<?php

namespace App\Services;

use App\Models\ClientRefund;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;

class UnifiedFinanceService
{
    public function summary(): array
    {
        $now =
            Carbon::now(
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

        $normalGross =
            $this->normalPayments();

        $normalRefund =
            $this->refunds();

        $normalReceived =
            round(
                $normalGross
                - $normalRefund,
                2
            );

        $hotspotReceived =
            $this->hotspotPayments();

        $normalTodayGross =
            $this->normalPayments(
                $today,
                $today
            );

        $normalTodayRefund =
            $this->refunds(
                $today,
                $today
            );

        $normalToday =
            round(
                $normalTodayGross
                - $normalTodayRefund,
                2
            );

        $hotspotToday =
            $this->hotspotPayments(
                $today,
                $today
            );

        $normalMonthGross =
            $this->normalPayments(
                $monthStart,
                $monthEnd
            );

        $normalMonthRefund =
            $this->refunds(
                $monthStart,
                $monthEnd
            );

        $normalMonth =
            round(
                $normalMonthGross
                - $normalMonthRefund,
                2
            );

        $hotspotMonth =
            $this->hotspotPayments(
                $monthStart,
                $monthEnd
            );

        $normalDue =
            round(
                (float)
                Invoice::query()
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
                HotspotInvoice::query()
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
            'normal_gross_received' =>
                $normalGross,

            'normal_refunded' =>
                $normalRefund,

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

            'normal_today_gross' =>
                $normalTodayGross,

            'normal_today_refunded' =>
                $normalTodayRefund,

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

            'normal_month_gross' =>
                $normalMonthGross,

            'normal_month_refunded' =>
                $normalMonthRefund,

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

    private function normalPayments(
        ?string $from = null,
        ?string $to = null
    ): float {
        $query =
            Payment::query();

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
            $query->sum(
                'amount'
            ),
            2
        );
    }

    private function hotspotPayments(
        ?string $from = null,
        ?string $to = null
    ): float {
        $query =
            HotspotPayment::query();

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
            $query->sum(
                'amount'
            ),
            2
        );
    }

    private function refunds(
        ?string $from = null,
        ?string $to = null
    ): float {
        $query =
            ClientRefund::query();

        if ($from !== null) {
            $query->whereDate(
                'refund_date',
                '>=',
                $from
            );
        }

        if ($to !== null) {
            $query->whereDate(
                'refund_date',
                '<=',
                $to
            );
        }

        return round(
            (float)
            $query->sum(
                'amount'
            ),
            2
        );
    }
}
