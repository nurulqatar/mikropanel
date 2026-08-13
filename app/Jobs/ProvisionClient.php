<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\ClientProvisionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionClient implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $backoff = 5;

    public function __construct(
        public int $clientId
    ) {
        /*
         * Reuse the existing background
         * RouterOS worker.
         */
        $this->onQueue(
            'router-sync'
        );
    }

    public function handle(
        ClientProvisionService $provision
    ): void {
        /*
         * Normal query intentionally excludes
         * soft-deleted/archived clients.
         *
         * If the operator archives the client
         * before this job starts, nothing is
         * provisioned.
         */
        $client = Client::query()
            ->find(
                $this->clientId
            );

        if (!$client) {
            return;
        }

        $provision->provision(
            $client
        );

        Log::info(
            'Background client provisioning completed.',
            [
                'client_id' =>
                    $client->id,

                'client_code' =>
                    $client->client_code,
            ]
        );
    }

    public function failed(
        Throwable $exception
    ): void {
        /*
         * Keep the panel client record.
         *
         * clients:sync-routers remains the
         * automatic convergence fallback.
         */
        Log::error(
            'Background client provisioning job failed.',
            [
                'client_id' =>
                    $this->clientId,

                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
}
