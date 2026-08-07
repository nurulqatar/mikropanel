<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-monthly
        {--date= : Billing run date in YYYY-MM-DD format}
        {--dry-run : Show what would be generated without creating invoices}';

    protected $description =
        'Generate monthly invoices for clients whose billing date is due';

    public function handle(): int
    {
        try {
            $runDate = $this->option('date')
                ? Carbon::parse(
                    $this->option('date'),
                    'Asia/Qatar'
                )->startOfDay()
                : Carbon::now(
                    'Asia/Qatar'
                )->startOfDay();
        } catch (Throwable) {
            $this->error(
                'Invalid --date. Use YYYY-MM-DD.'
            );

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option(
            'dry-run'
        );

        $graceDays = max(
            0,
            (int) Setting::getValue(
                'grace_days',
                0
            )
        );

        $defaultDueDays = max(
            0,
            (int) Setting::getValue(
                'default_due_days',
                0
            )
        );

        $billingMonth = $runDate
            ->copy()
            ->startOfMonth();

        $daysInMonth = $runDate
            ->daysInMonth;

        $stats = [
            'created' => 0,
            'would_create' => 0,
            'existing' => 0,
            'prepaid' => 0,
            'not_due' => 0,
            'invalid' => 0,
            'failed' => 0,
        ];

        $this->info(
            'Billing date: '
            . $runDate->toDateString()
        );

        $this->info(
            'Grace days: '
            . $graceDays
        );

        $this->info(
            'Default invoice due days: '
            . $defaultDueDays
        );

        if ($dryRun) {
            $this->warn(
                'DRY RUN: no invoices will be created.'
            );
        }

        Client::query()
            ->where('enabled', true)
            ->with('package')
            ->orderBy('id')
            ->chunkById(
                50,
                function ($clients) use (
                    $runDate,
                    $billingMonth,
                    $daysInMonth,
                    $defaultDueDays,
                    $dryRun,
                    &$stats
                ): void {
                    foreach ($clients as $client) {
                        try {
                            $billingDay = min(
                                max(
                                    1,
                                    (int) $client
                                        ->billing_day
                                ),
                                $daysInMonth
                            );

                            $billingDate =
                                Carbon::create(
                                    $runDate->year,
                                    $runDate->month,
                                    $billingDay,
                                    0,
                                    0,
                                    0,
                                    'Asia/Qatar'
                                );

                            /*
                             * Billing date এখনও না এলে
                             * invoice create হবে না।
                             */
                            if (
                                $billingDate->gt(
                                    $runDate
                                )
                            ) {
                                $stats['not_due']++;
                                continue;
                            }

                            /*
                             * Client billing date-এর পরে
                             * install হয়ে থাকলে skip।
                             */
                            if (
                                $client->installed_at
                                && $client
                                    ->installed_at
                                    ->copy()
                                    ->startOfDay()
                                    ->gt($billingDate)
                            ) {
                                $stats['not_due']++;
                                continue;
                            }

                            /*
                             * Client আগে থেকেই পরবর্তী
                             * billing date-এর beyond paid
                             * থাকলে নতুন invoice নয়।
                             *
                             * Early renewal-এর duplicate
                             * billing আটকাবে।
                             */
                            if (
                                $client->expiry_date
                                && $client
                                    ->expiry_date
                                    ->copy()
                                    ->startOfDay()
                                    ->gt($billingDate)
                            ) {
                                $stats['prepaid']++;
                                continue;
                            }

                            $alreadyExists =
                                Invoice::query()
                                    ->where(
                                        'client_id',
                                        $client->id
                                    )
                                    ->whereDate(
                                        'billing_month',
                                        $billingMonth
                                            ->toDateString()
                                    )
                                    ->exists();

                            if ($alreadyExists) {
                                $stats['existing']++;
                                continue;
                            }

                            $price = round(
                                (float) (
                                    $client
                                        ->package
                                        ?->price
                                    ?? 0
                                ),
                                2
                            );

                            if ($price <= 0) {
                                $stats['invalid']++;

                                $this->warn(
                                    "SKIP {$client->client_code}: "
                                    . 'invalid package price.'
                                );

                                continue;
                            }

                            if ($dryRun) {
                                $stats[
                                    'would_create'
                                ]++;

                                $this->line(
                                    "WOULD CREATE | "
                                    . "{$client->client_code}"
                                    . " | {$client->name}"
                                    . " | QAR "
                                    . number_format(
                                        $price,
                                        2
                                    )
                                    . " | Billing "
                                    . $billingDate
                                        ->toDateString()
                                );

                                continue;
                            }

                            $created = DB::transaction(
                                function () use (
                                    $client,
                                    $billingDate,
                                    $billingMonth,
                                    $defaultDueDays
                                ): bool {
                                    $locked =
                                        Client::query()
                                            ->with(
                                                'package'
                                            )
                                            ->lockForUpdate()
                                            ->find(
                                                $client->id
                                            );

                                    if (
                                        !$locked
                                        || !$locked
                                            ->enabled
                                    ) {
                                        return false;
                                    }

                                    /*
                                     * Transaction-এর ভিতরে
                                     * duplicate check আবার।
                                     */
                                    $exists =
                                        Invoice::query()
                                            ->where(
                                                'client_id',
                                                $locked->id
                                            )
                                            ->whereDate(
                                                'billing_month',
                                                $billingMonth
                                                    ->toDateString()
                                            )
                                            ->exists();

                                    if ($exists) {
                                        return false;
                                    }

                                    /*
                                     * Payment/renewal parallel
                                     * হলে prepaid check আবার।
                                     */
                                    if (
                                        $locked
                                            ->expiry_date
                                        && $locked
                                            ->expiry_date
                                            ->copy()
                                            ->startOfDay()
                                            ->gt(
                                                $billingDate
                                            )
                                    ) {
                                        return false;
                                    }

                                    $price = round(
                                        (float) (
                                            $locked
                                                ->package
                                                ?->price
                                            ?? 0
                                        ),
                                        2
                                    );

                                    if ($price <= 0) {
                                        return false;
                                    }

                                    $invoiceNo =
                                        'INV-AUTO-'
                                        . $billingMonth
                                            ->format(
                                                'Ym'
                                            )
                                        . '-'
                                        . $locked->id;

                                    Invoice::create([
                                        'client_id' =>
                                            $locked->id,

                                        'invoice_no' =>
                                            $invoiceNo,

                                        'billing_month' =>
                                            $billingMonth
                                                ->toDateString(),

                                        'amount' =>
                                            $price,

                                        'discount' => 0,
                                        'paid_amount' => 0,
                                        'due_amount' =>
                                            $price,

                                        'issue_date' =>
                                            $billingDate
                                                ->toDateString(),

                                        'due_date' =>
                                            $billingDate
                                                ->copy()
                                                ->addDays(
                                                    $defaultDueDays
                                                )
                                                ->toDateString(),

                                        'status' =>
                                            'unpaid',

                                        /*
                                         * Auto bill must apply
                                         * one service period only
                                         * after it is fully paid.
                                         */
                                        'applies_service_period' =>
                                            true,

                                        'service_applied_at' =>
                                            null,

                                        'notes' =>
                                            'Automatic monthly invoice',

                                        'created_by' =>
                                            null,
                                    ]);

                                    return true;
                                }
                            );

                            if ($created) {
                                $stats['created']++;

                                $this->info(
                                    "CREATED | "
                                    . "{$client->client_code}"
                                    . " | {$client->name}"
                                );
                            } else {
                                $stats['existing']++;
                            }
                        } catch (Throwable $e) {
                            $stats['failed']++;

                            $this->error(
                                "FAILED {$client->client_code}: "
                                . $e->getMessage()
                            );
                        }
                    }
                }
            );

        $this->newLine();

        $this->table(
            ['Result', 'Count'],
            [
                [
                    'Created',
                    $stats['created'],
                ],
                [
                    'Would Create',
                    $stats['would_create'],
                ],
                [
                    'Already Existing',
                    $stats['existing'],
                ],
                [
                    'Prepaid / Early Renewed',
                    $stats['prepaid'],
                ],
                [
                    'Billing Date Not Due',
                    $stats['not_due'],
                ],
                [
                    'Invalid Package',
                    $stats['invalid'],
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
}
