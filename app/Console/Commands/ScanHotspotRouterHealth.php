<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Hotspot\HotspotRouterAlertService;
use App\Services\Hotspot\HotspotRouterHealthService;
use Illuminate\Console\Command;
use Throwable;

class ScanHotspotRouterHealth extends Command
{
    protected $signature =
        'hotspot:health-scan
        {--router= : Scan only one Hotspot router ID}';

    protected $description =
        'Persist Hotspot Router Health problems and resolve recovered alerts';

    public function handle(
        HotspotRouterHealthService $health,
        HotspotRouterAlertService $alerts
    ): int {
        $query =
            Router::query()
                ->with([
                    'zone:id,reseller_id,name,code,service_type',
                ])
                ->where(
                    'enabled',
                    true
                )
                ->whereHas(
                    'zone',
                    fn ($query) =>
                        $query->where(
                            'service_type',
                            'hotspot'
                        )
                )
                ->orderBy('id');

        if (
            $this->option(
                'router'
            )
        ) {
            $query->where(
                'id',
                (int)
                $this->option(
                    'router'
                )
            );
        }

        $routers =
            $query->get();

        $scanned = 0;
        $failed = 0;

        foreach (
            $routers
            as $router
        ) {
            try {
                $snapshot =
                    $health->snapshot(
                        $router
                    );

                $counts =
                    $alerts->reconcile(
                        $router,
                        $snapshot
                    );

                $this->line(
                    'ROUTER='
                    . $router->id
                    . ' NAME='
                    . $router->name
                    . ' ONLINE='
                    . (
                        (
                            $snapshot[
                                'online'
                            ] ?? false
                        )
                            ? 'YES'
                            : 'NO'
                    )
                    . ' ALERTS='
                    . $counts[
                        'active'
                    ]
                    . ' CRITICAL='
                    . $counts[
                        'critical'
                    ]
                    . ' WARNING='
                    . $counts[
                        'warning'
                    ]
                );

                $scanned++;

            } catch (Throwable $exception) {
                $failed++;

                $this->error(
                    'ROUTER='
                    . $router->id
                    . ' ERROR='
                    . $exception
                        ->getMessage()
                );
            }
        }

        $this->info(
            "SCANNED={$scanned} FAILED={$failed}"
        );

        return self::SUCCESS;
    }
}
