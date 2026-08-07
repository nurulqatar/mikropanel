<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Setting;
use App\Services\ClientProvisionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SuspendExpiredClients extends Command
{
    protected $signature =
        'clients:suspend-expired
        {--dry-run : Show clients without suspending them}';

    protected $description =
        'Suspend enabled clients after expiry and configured grace period';

    public function handle(
        ClientProvisionService $provision
    ): int {
        $suspended = 0;
        $failed = 0;

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

        $today = Carbon::today(
            'Asia/Qatar'
        );

        /*
         * grace=0:
         * expiry < today
         *
         * grace=3:
         * expiry + 3 days পার হওয়ার
         * পর suspend।
         */
        $cutoffDate = $today
            ->copy()
            ->subDays($graceDays);

        $this->info(
            "Grace days: {$graceDays}"
        );

        $this->info(
            'Suspend clients with expiry before: '
            . $cutoffDate->toDateString()
        );

        Client::query()
            ->where('enabled', true)
            ->whereNotNull('expiry_date')
            ->whereDate(
                'expiry_date',
                '<',
                $cutoffDate->toDateString()
            )
            ->with([
                'router',
                'package',
                'ipRange',
            ])
            ->orderBy('id')
            ->chunkById(
                50,
                function ($clients) use (
                    $provision,
                    $dryRun,
                    $graceDays,
                    &$suspended,
                    &$failed
                ): void {
                    foreach ($clients as $client) {
                        $graceUntil =
                            $client
                                ->expiry_date
                                ?->copy()
                                ->addDays(
                                    $graceDays
                                );

                        if ($dryRun) {
                            $this->line(
                                "{$client->client_code}"
                                . " | {$client->name}"
                                . " | Expiry: "
                                . $client
                                    ->expiry_date
                                    ?->format(
                                        'Y-m-d'
                                    )
                                . " | Grace until: "
                                . $graceUntil
                                    ?->format(
                                        'Y-m-d'
                                    )
                            );

                            continue;
                        }

                        try {
                            $provision->suspend(
                                $client
                            );

                            $suspended++;

                            $this->info(
                                "Suspended: "
                                . "{$client->client_code}"
                                . " - {$client->name}"
                            );
                        } catch (
                            Throwable $exception
                        ) {
                            $failed++;

                            Log::error(
                                'Automatic client suspension failed.',
                                [
                                    'client_id' =>
                                        $client->id,

                                    'client_code' =>
                                        $client
                                            ->client_code,

                                    'grace_days' =>
                                        $graceDays,

                                    'message' =>
                                        $exception
                                            ->getMessage(),
                                ]
                            );

                            $this->error(
                                "Failed: "
                                . "{$client->client_code}"
                                . " - "
                                . $exception
                                    ->getMessage()
                            );
                        }
                    }
                }
            );

        if ($dryRun) {
            $this->newLine();

            $this->info(
                'Dry run completed. No client was changed.'
            );

            return self::SUCCESS;
        }

        $this->newLine();

        $this->info(
            "Suspended clients: {$suspended}"
        );

        if ($failed > 0) {
            $this->error(
                "Failed clients: {$failed}"
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
