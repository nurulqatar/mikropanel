<?php

namespace App\Console\Commands;

use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelBillingInvoice;
use App\Models\Hotel\HotelNotification;
use App\Models\Hotel\HotelRouter;
use App\Services\Hotel\HotelCommercialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HotelCommercialMaintain extends Command
{
    protected $signature =
        'hotel-hotspot:commercial-maintain
         {--dry-run : Check without changing business data}';

    protected $description =
        'Maintain Hotel billing, subscription state, router alerts and sync notifications.';

    public function handle(
        HotelCommercialService $commercial
    ): int {
        $dry =
            (bool)
            $this->option(
                'dry-run'
            );

        $stats = [
            'subscriptions' => 0,
            'invoices' => 0,
            'expired' => 0,
            'reactivated' => 0,
            'alerts' => 0,
        ];

        $subscriptions =
            DB::table(
                'hotel_subscriptions'
            )
                ->where(
                    'status',
                    'active'
                )
                ->orderBy('id')
                ->get();

        foreach (
            $subscriptions
            as $subscription
        ) {
            $stats['subscriptions']++;

            $hotel =
                Hotel::query()
                    ->find(
                        $subscription
                            ->hotel_id
                    );

            if (!$hotel) {
                continue;
            }

            if (!$dry) {
                $before =
                    HotelBillingInvoice::query()
                        ->where(
                            'hotel_subscription_id',
                            $subscription->id
                        )
                        ->exists();

                $invoice =
                    $commercial
                        ->invoiceForSubscription(
                            $subscription
                        );

                if (!$before) {
                    $stats['invoices']++;
                }

                if (
                    $invoice->due_amount > 0
                    && $invoice
                        ->due_date
                        ->lt(today())
                ) {
                    $invoice->forceFill([
                        'status' =>
                            'overdue',
                    ])->save();

                    $commercial->notify(
                        $hotel->id,
                        'billing',
                        'danger',
                        'Hotel invoice overdue',
                        'Invoice '
                        . $invoice->invoice_no
                        . ' has an outstanding balance of '
                        . $hotel->currency
                        . ' '
                        . $invoice->due_amount
                        . '.',
                        'invoice:'
                        . $invoice->id
                        . ':overdue',
                        '/hotel/billing'
                    );

                    $stats['alerts']++;
                }
            }

            if (
                empty(
                    $subscription
                        ->expires_at
                )
            ) {
                continue;
            }

            $expires =
                \Carbon\CarbonImmutable::parse(
                    $subscription
                        ->expires_at
                );

            if (
                !$dry
                && $expires
                    ->isFuture()
                && $expires
                    ->lte(
                        now()
                            ->addDays(7)
                    )
            ) {
                $commercial->notify(
                    $hotel->id,
                    'subscription',
                    'warning',
                    'Subscription expiring soon',
                    'Your Hotel Hotspot subscription expires on '
                    . $expires
                        ->timezone(
                            $hotel->timezone
                            ?: 'Asia/Qatar'
                        )
                        ->format(
                            'Y-m-d H:i'
                        )
                    . '.',
                    'subscription:'
                    . $subscription->id
                    . ':expiry',
                    '/hotel/billing'
                );

                $stats['alerts']++;
            }

            if ($expires->lte(now())) {
                $stats['expired']++;

                if (
                    !$dry
                    && $hotel->status
                        === 'active'
                ) {
                    $hotel->forceFill([
                        'status' =>
                            'suspended',

                        'suspended_at' =>
                            now(),

                        'suspension_reason' =>
                            '[AUTO-SUBSCRIPTION] Subscription expired.',
                    ])->save();

                    $commercial->notify(
                        $hotel->id,
                        'subscription',
                        'danger',
                        'Hotel Hotspot suspended',
                        'Subscription expired. Renew the Hotel plan to reactivate service.',
                        'subscription:'
                        . $subscription->id
                        . ':suspended',
                        '/hotel/billing'
                    );
                }

                continue;
            }

            if (
                !$dry
                && $hotel->status
                    === 'suspended'
                && str_starts_with(
                    (string)
                    $hotel
                        ->suspension_reason,
                    '[AUTO-SUBSCRIPTION]'
                )
            ) {
                $hotel->forceFill([
                    'status' =>
                        'active',

                    'suspended_at' =>
                        null,

                    'suspension_reason' =>
                        null,
                ])->save();

                $stats['reactivated']++;
            }
        }

        $routers =
            HotelRouter::query()
                ->where(
                    'enabled',
                    true
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereNotNull(
                                'last_error'
                            )
                            ->orWhereIn(
                                'status',
                                [
                                    'failed',
                                    'offline',
                                    'error',
                                ]
                            );
                    }
                )
                ->get();

        foreach ($routers as $router) {
            if ($dry) {
                $stats['alerts']++;
                continue;
            }

            $commercial->notify(
                $router->hotel_id,
                'router',
                'danger',
                'MikroTik requires attention',
                $router->name
                . ': '
                . (
                    $router->last_error
                    ?: 'Router status is '
                    . $router->status
                ),
                'router:'
                . $router->id
                . ':health',
                '/hotel/routers'
            );

            $stats['alerts']++;
        }

        $failedSyncs =
            DB::table(
                'hotel_voucher_router_syncs as s'
            )
                ->join(
                    'hotel_vouchers as v',
                    'v.id',
                    '=',
                    's.hotel_voucher_id'
                )
                ->where(
                    's.status',
                    'failed'
                )
                ->select([
                    's.id',
                    's.last_error',
                    'v.hotel_id',
                ])
                ->limit(500)
                ->get();

        foreach (
            $failedSyncs
            as $sync
        ) {
            if ($dry) {
                $stats['alerts']++;
                continue;
            }

            $commercial->notify(
                $sync->hotel_id,
                'voucher_sync',
                'danger',
                'Voucher router sync failed',
                $sync->last_error
                    ?: 'A voucher could not be synchronized to a MikroTik router.',
                'voucher-sync:'
                . $sync->id
                . ':failed',
                '/hotel/vouchers'
            );

            $stats['alerts']++;
        }

        if (!$dry) {
            HotelNotification::query()
                ->whereNotNull(
                    'resolved_at'
                )
                ->where(
                    'resolved_at',
                    '<',
                    now()
                        ->subDays(180)
                )
                ->delete();
        }

        foreach ($stats as $key => $value) {
            $this->line(
                strtoupper($key)
                . '='
                . $value
            );
        }

        $this->line(
            'DRY_RUN='
            . (
                $dry
                    ? 'YES'
                    : 'NO'
            )
        );

        return self::SUCCESS;
    }
}
