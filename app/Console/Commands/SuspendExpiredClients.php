<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientProvisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SuspendExpiredClients extends Command
{
    protected $signature = 'clients:suspend-expired
                            {--dry-run : Show expired clients without suspending them}';

    protected $description = 'Suspend enabled clients whose expiry date has passed';

    public function handle(
        ClientProvisionService $provision
    ): int {
        $suspended = 0;
        $failed = 0;
        $dryRun = (bool) $this->option('dry-run');

        Client::query()
            ->where('enabled', true)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->with([
                'router',
                'package',
                'ipRange',
            ])
            ->orderBy('id')
            ->chunkById(50, function ($clients) use (
                $provision,
                $dryRun,
                &$suspended,
                &$failed
            ) {
                foreach ($clients as $client) {
                    if ($dryRun) {
                        $this->line(
                            "{$client->client_code} | " .
                            "{$client->name} | " .
                            "Expiry: {$client->expiry_date?->format('Y-m-d')}"
                        );

                        continue;
                    }

                    try {
                        $provision->suspend($client);

                        $suspended++;

                        $this->info(
                            "Suspended: {$client->client_code} - {$client->name}"
                        );
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::error(
                            'Automatic client suspension failed.',
                            [
                                'client_id' => $client->id,
                                'client_code' => $client->client_code,
                                'message' => $exception->getMessage(),
                            ]
                        );

                        $this->error(
                            "Failed: {$client->client_code} - " .
                            $exception->getMessage()
                        );
                    }
                }
            });

        if ($dryRun) {
            $this->info('Dry run completed. No client was changed.');

            return self::SUCCESS;
        }

        $this->newLine();

        $this->info("Suspended clients: {$suspended}");

        if ($failed > 0) {
            $this->error("Failed clients: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
