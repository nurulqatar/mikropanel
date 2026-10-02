<?php

namespace App\Jobs;

use App\Models\HotspotServer;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RouterOS\Exceptions\ConnectException;
use Throwable;

class SyncHotspotServer implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;
    public int $backoff = 5;
    public int $uniqueFor = 3600;

    public function __construct(
        public int $serverId
    ) {
        $this->onQueue(
            'router-sync'
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public function handle(
        HotspotRouterService $service
    ): void {
        $server =
            HotspotServer::query()
                ->where('enabled', true)
                ->find(
                    $this->serverId
                );

        if (!$server) {
            return;
        }

        try {
            $service->syncServer(
                $server
            );

        } catch (ConnectException $exception) {
            /*
             * ROUTER_OFFLINE_IS_STATE_V1
             *
             * A powered-off / unreachable router is
             * an operational state, not a queue failure.
             * The scheduler will try again later.
             */
            $server->forceFill([
                'connected' => false,

                'last_synced_at' =>
                    now(),

                'last_error' =>
                    $exception
                        ->getMessage(),
            ])->save();

            return;

        } catch (Throwable $exception) {
            $server->forceFill([
                'connected' => false,

                'last_synced_at' =>
                    now(),

                'last_error' =>
                    $exception
                        ->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function failed(
        Throwable $exception
    ): void {
        Log::error(
            'Hotspot server sync failed.',
            [
                'server_id' =>
                    $this->serverId,

                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
}
