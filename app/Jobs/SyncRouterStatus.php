<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\RouterTelemetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncRouterStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $backoff = 5;

    public function __construct(
        public int $routerId
    ) {
        $this->onQueue(
            'router-sync'
        );
    }

    public function handle(
        RouterTelemetryService $telemetry
    ): void {
        $router =
            Router::query()
                ->find(
                    $this->routerId
                );

        if (!$router) {
            return;
        }

        $telemetry->sync(
            $router
        );
    }
}
