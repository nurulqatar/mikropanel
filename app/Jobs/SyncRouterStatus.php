<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\Hotspot\HotspotRouterService;
use App\Services\RouterTelemetryService;
use Throwable;
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
        RouterTelemetryService $telemetry,
        HotspotRouterService $hotspot
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

        if (!$router->enabled) {
            return;
        }

        $router->loadMissing([
            'zone:id,service_type',
        ]);

        if (
            !$router->zone
            || $router
                ->zone
                ->service_type !== 'hotspot'
        ) {
            return;
        }

        $host =
            parse_url(
                (string)
                config(
                    'app.url'
                ),
                PHP_URL_HOST
            );

        if (
            !is_string($host)
            || trim($host) === ''
        ) {
            return;
        }

        try {
            $hotspot
                ->ensurePortalHostAccess(
                    $router,
                    $host
                );

        } catch (Throwable $exception) {
            /*
             * Portal access refresh must never turn
             * a temporary router outage into a
             * failed telemetry job.
             */
            report(
                $exception
            );
        }
    }
}
