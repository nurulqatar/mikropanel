<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientMonthlyUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientUsageService
{
    public function __construct(
        private QueueUsageService $queueUsage
    ) {
    }

    public function syncAll(): array
    {
        $stats = [
            'synced' => 0,
            'baselined' => 0,
            'missing' => 0,
            'failed' => 0,
        ];

        $clients = Client::query()
            ->with('router')
            ->whereNotNull('router_id')
            ->whereNotNull('client_code')
            ->get();

        $groups = $clients->groupBy('router_id');

        foreach ($groups as $routerClients) {
            $router = $routerClients
                ->first()
                ?->router;

            if (!$router || !$router->enabled) {
                $stats['failed'] +=
                    $routerClients->count();

                continue;
            }

            try {
                $queues = $this->queueUsage
                    ->readForRouter($router);
            } catch (Throwable $exception) {
                $stats['failed'] +=
                    $routerClients->count();

                Log::error(
                    'Client usage router sync failed.',
                    [
                        'router_id' => $router->id,
                        'message' =>
                            $exception->getMessage(),
                    ]
                );

                continue;
            }

            foreach ($routerClients as $client) {
                $counter = $queues[
                    $client->client_code
                ] ?? null;

                if (!$counter) {
                    $stats['missing']++;

                    continue;
                }

                $status = $this->storeCounters(
                    $client,
                    $counter
                );

                $stats[$status]++;
            }
        }

        return $stats;
    }

    public function syncClient(
        Client $client
    ): string {
        $client->loadMissing('router');

        if (!$client->router) {
            return 'missing';
        }

        $queues = $this->queueUsage
            ->readForRouter($client->router);

        $counter = $queues[
            $client->client_code
        ] ?? null;

        if (!$counter) {
            return 'missing';
        }

        return $this->storeCounters(
            $client,
            $counter
        );
    }

    private function storeCounters(
        Client $client,
        array $counter
    ): string {
        $month = today()
            ->startOfMonth()
            ->toDateString();

        $currentUpload = max(
            0,
            (int) (
                $counter['upload_bytes'] ?? 0
            )
        );

        $currentDownload = max(
            0,
            (int) (
                $counter['download_bytes'] ?? 0
            )
        );

        return DB::transaction(
            function () use (
                $client,
                $month,
                $currentUpload,
                $currentDownload
            ): string {
                $usage = ClientMonthlyUsage::query()
                    ->where(
                        'client_id',
                        $client->id
                    )
                    ->where(
                        'usage_month',
                        $month
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                 * প্রথম sync-এ বর্তমান Queue counter
                 * baseline হিসেবে রাখা হবে।
                 *
                 * পুরোনো মাসের traffic current month-এ
                 * ভুলভাবে যোগ হবে না।
                 */
                if (!$usage) {
                    ClientMonthlyUsage::create([
                        'client_id' => $client->id,
                        'usage_month' => $month,
                        'upload_bytes' => 0,
                        'download_bytes' => 0,
                        'last_upload_counter' =>
                            $currentUpload,

                        'last_download_counter' =>
                            $currentDownload,

                        'last_synced_at' => now(),
                    ]);

                    return 'baselined';
                }

                $lastUpload = (int)
                    $usage->last_upload_counter;

                $lastDownload = (int)
                    $usage->last_download_counter;

                /*
                 * Current counter ছোট হলে Router অথবা
                 * Queue counter reset হয়েছে।
                 */
                $uploadDelta =
                    $currentUpload >= $lastUpload
                        ? $currentUpload - $lastUpload
                        : $currentUpload;

                $downloadDelta =
                    $currentDownload >= $lastDownload
                        ? $currentDownload - $lastDownload
                        : $currentDownload;

                $usage->update([
                    'upload_bytes' =>
                        (int) $usage->upload_bytes
                        + $uploadDelta,

                    'download_bytes' =>
                        (int) $usage->download_bytes
                        + $downloadDelta,

                    'last_upload_counter' =>
                        $currentUpload,

                    'last_download_counter' =>
                        $currentDownload,

                    'last_synced_at' => now(),
                ]);

                return 'synced';
            }
        );
    }
}
