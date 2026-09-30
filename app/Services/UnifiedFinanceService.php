<?php

namespace App\Services;

use App\Models\ClientRefund;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotSellerCollection;
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

        $hotspotCustomerDue =
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

        /*
         * Seller voucher is already paid by the
         * customer, but cash remains receivable
         * from the seller until collection.
         */
        $sellerOutstanding =
            $this->sellerOutstanding();

        $hotspotDue =
            round(
                $hotspotCustomerDue
                + $sellerOutstanding,
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

            'hotspot_customer_due' =>
                $hotspotCustomerDue,

            'hotspot_seller_outstanding' =>
                $sellerOutstanding,

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
        /*
         * Hotspot cash has two legitimate sources:
         *
         * 1) Direct HotspotPayment rows.
         * 2) Cash collected from voucher sellers.
         *
         * Voucher first-login sale itself is NOT
         * company cash because the seller still
         * physically holds that money.
         */
        $direct =
            HotspotPayment::query();

        $seller =
            HotspotSellerCollection::query();

        if ($from !== null) {
            $direct->whereDate(
                'payment_date',
                '>=',
                $from
            );

            $seller->whereDate(
                'collected_at',
                '>=',
                $from
            );
        }

        if ($to !== null) {
            $direct->whereDate(
                'payment_date',
                '<=',
                $to
            );

            $seller->whereDate(
                'collected_at',
                '<=',
                $to
            );
        }

        return round(
            (float)
            $direct->sum(
                'amount'
            )
            +
            (float)
            $seller->sum(
                'amount'
            ),
            2
        );
    }

    private function sellerOutstanding(): float
    {
        $sales =
            (float)
            HotspotInvoice::query()
                ->whereNotNull(
                    'hotspot_seller_id'
                )
                ->where(
                    'invoice_type',
                    'sale'
                )
                ->where(
                    'status',
                    '!=',
                    'cancelled'
                )
                ->selectRaw(
                    '
                        COALESCE(
                            SUM(
                                amount
                                - discount
                            ),
                            0
                        ) AS total
                    '
                )
                ->value(
                    'total'
                );

        $collected =
            (float)
            HotspotSellerCollection::query()
                ->sum(
                    'amount'
                );

        return max(
            0,
            round(
                $sales
                - $collected,
                2
            )
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
