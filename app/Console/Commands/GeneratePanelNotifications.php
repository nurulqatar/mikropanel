<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PanelNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class GeneratePanelNotifications extends Command
{
    protected $signature =
        'notifications:generate-panel
        {--date= : Run date in YYYY-MM-DD format}
        {--dry-run : Show notifications without saving them}';

    protected $description =
        'Generate internal payment, due and expiry notifications';

    public function handle(
        PanelNotificationService $notifier
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

        $dryRun = (bool)
            $this->option('dry-run');

        $reminderDays = max(
            0,
            (int) Setting::getValue(
                'reminder_days_before',
                3
            )
        );

        $until = $today
            ->copy()
            ->addDays($reminderDays);

        $stats = [
            'events' => 0,
            'deliveries' => 0,
            'payments' => 0,
            'due' => 0,
            'expiry' => 0,
            'failed' => 0,
        ];

        $this->info(
            'Notification date: '
            . $today->toDateString()
        );

        $this->info(
            'Reminder window: '
            . $reminderDays
            . ' day(s)'
        );

        if ($dryRun) {
            $this->warn(
                'DRY RUN: nothing will be saved.'
            );
        }

        if (
            Setting::bool(
                'payment_receipt_enabled',
                true
            )
        ) {
            Payment::query()
                ->with([
                    'client',
                    'invoice',
                ])
                ->whereDate(
                    'payment_date',
                    $today->toDateString()
                )
                ->orderBy('id')
                ->chunkById(
                    100,
                    function (
                        $payments
                    ) use (
                        $notifier,
                        $dryRun,
                        &$stats
                    ): void {
                        foreach (
                            $payments
                            as $payment
                        ) {
                            try {
                                $clientName =
                                    $payment
                                        ->client
                                        ?->name
                                    ?? 'Client';

                                $due = round(
                                    (float) (
                                        $payment
                                            ->invoice
                                            ?->due_amount
                                        ?? 0
                                    ),
                                    2
                                );

                                $template =
                                    (string)
                                    Setting::getValue(
                                        'payment_message_template',
                                        'Payment received: QAR {amount}. Remaining due: QAR {due}. Thank you.'
                                    );

                                $message =
                                    $this
                                        ->renderTemplate(
                                            $template,
                                            [
                                                'client' =>
                                                    $clientName,

                                                'amount' =>
                                                    number_format(
                                                        (float)
                                                        $payment
                                                            ->amount,
                                                        2
                                                    ),

                                                'due' =>
                                                    number_format(
                                                        $due,
                                                        2
                                                    ),
                                            ]
                                        );

                                $deliveries =
                                    $notifier
                                        ->sendToActiveUsers(
                                            'payment:'
                                            . $payment->id,

                                            'payment',

                                            'Payment Received — '
                                            . $clientName,

                                            $message,

                                            [
                                                'payment_id' =>
                                                    $payment->id,

                                                'client_id' =>
                                                    $payment
                                                        ->client_id,

                                                'invoice_id' =>
                                                    $payment
                                                        ->invoice_id,
                                            ],

                                            $dryRun
                                        );

                                if ($deliveries > 0) {
                                    $stats['events']++;
                                    $stats['payments']++;
                                    $stats['deliveries'] +=
                                        $deliveries;
                                }
                            } catch (Throwable $e) {
                                $stats['failed']++;

                                $this->error(
                                    'Payment notification failed: '
                                    . $e->getMessage()
                                );
                            }
                        }
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
                ->chunkById(
                    100,
                    function (
                        $invoices
                    ) use (
                        $notifier,
                        $dryRun,
                        &$stats
                    ): void {
                        foreach (
                            $invoices
                            as $invoice
                        ) {
                            try {
                                $clientName =
                                    $invoice
                                        ->client
                                        ?->name
                                    ?? 'Client';

                                $due = number_format(
                                    (float)
                                    $invoice
                                        ->due_amount,
                                    2
                                );

                                $template =
                                    (string)
                                    Setting::getValue(
                                        'due_message_template',
                                        'Dear {client}, your outstanding balance is QAR {due}.'
                                    );

                                $message =
                                    $this
                                        ->renderTemplate(
                                            $template,
                                            [
                                                'client' =>
                                                    $clientName,

                                                'due' => $due,

                                                'due_date' =>
                                                    $invoice
                                                        ->due_date
                                                        ?->format(
                                                            'Y-m-d'
                                                        ),
                                            ]
                                        );

                                $fingerprint =
                                    'invoice-due:'
                                    . $invoice->id
                                    . ':'
                                    . $invoice
                                        ->due_date
                                        ->format(
                                            'Y-m-d'
                                        );

                                $deliveries =
                                    $notifier
                                        ->sendToActiveUsers(
                                            $fingerprint,

                                            'due',

                                            'Payment Due — '
                                            . $clientName,

                                            $message,

                                            [
                                                'invoice_id' =>
                                                    $invoice->id,

                                                'client_id' =>
                                                    $invoice
                                                        ->client_id,

                                                'due_date' =>
                                                    $invoice
                                                        ->due_date
                                                        ->format(
                                                            'Y-m-d'
                                                        ),
                                            ],

                                            $dryRun
                                        );

                                if ($deliveries > 0) {
                                    $stats['events']++;
                                    $stats['due']++;
                                    $stats['deliveries'] +=
                                        $deliveries;
                                }
                            } catch (Throwable $e) {
                                $stats['failed']++;

                                $this->error(
                                    'Due notification failed: '
                                    . $e->getMessage()
                                );
                            }
                        }
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
                ->chunkById(
                    100,
                    function (
                        $clients
                    ) use (
                        $notifier,
                        $dryRun,
                        &$stats
                    ): void {
                        foreach (
                            $clients
                            as $client
                        ) {
                            try {
                                $expiryDate =
                                    $client
                                        ->expiry_date
                                        ->format(
                                            'Y-m-d'
                                        );

                                $template =
                                    (string)
                                    Setting::getValue(
                                        'expiry_message_template',
                                        'Dear {client}, your internet package will expire on {expiry_date}.'
                                    );

                                $message =
                                    $this
                                        ->renderTemplate(
                                            $template,
                                            [
                                                'client' =>
                                                    $client->name,

                                                'expiry_date' =>
                                                    $expiryDate,
                                            ]
                                        );

                                $deliveries =
                                    $notifier
                                        ->sendToActiveUsers(
                                            'client-expiry:'
                                            . $client->id
                                            . ':'
                                            . $expiryDate,

                                            'expiry',

                                            'Package Expiry — '
                                            . $client->name,

                                            $message,

                                            [
                                                'client_id' =>
                                                    $client->id,

                                                'expiry_date' =>
                                                    $expiryDate,
                                            ],

                                            $dryRun
                                        );

                                if ($deliveries > 0) {
                                    $stats['events']++;
                                    $stats['expiry']++;
                                    $stats['deliveries'] +=
                                        $deliveries;
                                }
                            } catch (Throwable $e) {
                                $stats['failed']++;

                                $this->error(
                                    'Expiry notification failed: '
                                    . $e->getMessage()
                                );
                            }
                        }
                    }
                );
        }

        $this->table(
            [
                'Result',
                'Count',
            ],
            [
                [
                    'Events',
                    $stats['events'],
                ],
                [
                    'User Deliveries',
                    $stats['deliveries'],
                ],
                [
                    'Payment Events',
                    $stats['payments'],
                ],
                [
                    'Due Events',
                    $stats['due'],
                ],
                [
                    'Expiry Events',
                    $stats['expiry'],
                ],
                [
                    'Failed',
                    $stats['failed'],
                ],
            ]
        );

        return $stats['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function renderTemplate(
        string $template,
        array $values
    ): string {
        $replace = [];

        foreach (
            $values
            as $key => $value
        ) {
            $replace[
                '{' . $key . '}'
            ] = (string) (
                $value ?? ''
            );
        }

        return strtr(
            $template,
            $replace
        );
    }
}
