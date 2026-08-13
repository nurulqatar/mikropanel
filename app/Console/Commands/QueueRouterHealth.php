<?php

namespace App\Console\Commands;

use App\Jobs\SyncRouterStatus;
use App\Models\Router;
use Illuminate\Console\Command;

class QueueRouterHealth extends Command
{
    protected $signature =
        'routers:queue-health';

    protected $description =
        'Queue background MikroTik health snapshots for enabled routers';

    public function handle(): int
    {
        $routerIds = Router::query()
            ->where(
                'enabled',
                true
            )
            ->orderBy('id')
            ->pluck('id');

        foreach ($routerIds as $routerId) {
            SyncRouterStatus::dispatch(
                (int) $routerId
            );
        }

        $this->info(
            'Queued router health jobs: '
            . $routerIds->count()
        );

        return self::SUCCESS;
    }
}
