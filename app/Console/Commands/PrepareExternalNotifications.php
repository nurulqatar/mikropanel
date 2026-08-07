<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\ExternalNotificationDeliveryService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class PrepareExternalNotifications extends Command
{
    protected $signature =
        'notifications:prepare-external
        {--date= : Run date in YYYY-MM-DD format}';

    protected $description =
        'Prepare retry-safe external notification delivery records';

    public function handle(
        ExternalNotificationDeliveryService $delivery
    ): int {
        try {
            $today = $this->option('date')
                ? Carbon::parse(
                    $this->option('date'),
                    'Asia/Qatar'
                )->startOfDay()
                : Carbon::today(
                    'Asia/Qatar'
                );
        } catch (Throwable) {
            $this->error(
                'Invalid --date. Use YYYY-MM-DD.'
            );

            return self::FAILURE;
        }

        $days = max(
            0,
            (int) Setting::getValue(
                'reminder_days_before',
                3
            )
        );

        $until =
            $today
                ->copy()
                ->addDays($days);

        $prepared = 0;

        if (
            Setting::bool(
                'payment_receipt_enabled',
                true
            )
        ) {
            Payment::query()
                ->with('client')
                ->whereDate(
                    'payment_date',
                    $today->toDateString()
                )
                ->orderBy('id')
                ->each(
                    function (
                        Payment $payment
                    ) use (
                        $delivery,
                        &$prepared
                    ): void {
                        $client =
                            $payment->client;

                        if (!$client) {
                            return;
                        }

                        $message =
                            'Payment received: QAR '
                            . number_format(
                                (float)
                                $payment->amount,
                                2
                            )
                            . '.';

                        $delivery->prepare(
                            'payment:'
                            . $payment->id,

                            $client,

                            'Payment Received',

                            $message
                        );

                        $prepared++;
                    }
                );
        }

        if (
            Setting::bool(
                'due_reminder_enabled',
                true
            )
        ) {
            Invoice::query()
                ->with('client')
                ->whereNotIn(
                    'status',
                    [
                        'paid',
                        'cancelled',
                    ]
                )
                ->where(
                    'due_amount',
                    '>',
                    0
                )
                ->whereBetween(
                    'due_date',
                    [
                        $today
                            ->toDateString(),

                        $until
                            ->toDateString(),
                    ]
                )
                ->orderBy('id')
                ->each(
                    function (
                        Invoice $invoice
                    ) use (
                        $delivery,
                        &$prepared
                    ): void {
                        $client =
                            $invoice->client;

                        if (!$client) {
                            return;
                        }

                        $message =
                            'Outstanding balance: QAR '
                            . number_format(
                                (float)
                                $invoice
                                    ->due_amount,
                                2
                            )
                            . '. Due date: '
                            . $invoice
                                ->due_date
                                ->format(
                                    'Y-m-d'
                                )
                            . '.';

                        $delivery->prepare(
                            'invoice-due:'
                            . $invoice->id
                            . ':'
                            . $invoice
                                ->due_date
                                ->format(
                                    'Y-m-d'
                                ),

                            $client,

                            'Payment Due',

                            $message
                        );

                        $prepared++;
                    }
                );
        }

        if (
            Setting::bool(
                'expiry_reminder_enabled',
                true
            )
        ) {
            Client::query()
                ->where(
                    'enabled',
                    true
                )
                ->whereNotNull(
                    'expiry_date'
                )
                ->whereBetween(
                    'expiry_date',
                    [
                        $today
                            ->toDateString(),

                        $until
                            ->toDateString(),
                    ]
                )
                ->orderBy('id')
                ->each(
                    function (
                        Client $client
                    ) use (
                        $delivery,
                        &$prepared
                    ): void {
                        $expiry =
                            $client
                                ->expiry_date
                                ->format(
                                    'Y-m-d'
                                );

                        $delivery->prepare(
                            'client-expiry:'
                            . $client->id
                            . ':'
                            . $expiry,

                            $client,

                            'Package Expiry',

                            'Your internet package will expire on '
                            . $expiry
                            . '.'
                        );

                        $prepared++;
                    }
                );
        }

        $this->info(
            "EVENTS_SCANNED={$prepared}"
        );

        /*
         * This command never performs an
         * external HTTP/SMTP request.
         */
        $this->info(
            'EXTERNAL_NETWORK_REQUESTS=0'
        );

        return self::SUCCESS;
    }
}
