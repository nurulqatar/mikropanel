<?php

namespace App\Jobs;

use App\Models\HotspotSession;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DisconnectHotspotSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;
    public int $backoff = 5;

    public function __construct(
        public int $sessionId
    ) {
        $this->onQueue(
            'router-sync'
        );
    }

    public function handle(
        HotspotRouterService $service
    ): void {
        $session =
            HotspotSession::query()
                ->find(
                    $this->sessionId
                );

        if (
            !$session
            || !$session->active
        ) {
            return;
        }

        $service->disconnectSession(
            $session
        );

        $session->forceFill([
            'active' => false,
            'ended_at' => now(),
        ])->save();
    }

    public function failed(
        Throwable $exception
    ): void {
        Log::error(
            'Hotspot session disconnect failed.',
            [
                'session_id' =>
                    $this->sessionId,

                'message' =>
                    $exception
                        ->getMessage(),
            ]
        );
    }
}
