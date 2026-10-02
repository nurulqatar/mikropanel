<?php

namespace App\Console\Commands;

use App\Models\Compliance\Router;
use App\Services\Compliance\ComplianceEntitlementService;
use App\Services\Compliance\ComplianceIdentitySyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncComplianceIdentities extends Command
{
    protected $signature = 'compliance:sync-identities';
    protected $description = 'Sync Compliance DHCP/Hotspot identity mappings.';

    public function handle(
        ComplianceEntitlementService $entitlements,
        ComplianceIdentitySyncService $identitySync
    ): int {
        $total = 0;
        $success = 0;
        $failed = 0;

        Router::query()
            ->where('enabled', true)
            ->where('vendor', 'mikrotik')
            ->orderBy('id')
            ->chunkById(25, function ($routers) use (
                $entitlements,
                $identitySync,
                &$total,
                &$success,
                &$failed
            ): void {
                foreach ($routers as $router) {
                    if (!$entitlements->logging($router->organization_id)) {
                        continue;
                    }

                    $total++;

                    try {
                        $identitySync->sync($router);
                        $router->forceFill(['last_error' => null])->save();
                        $success++;
                    } catch (Throwable $e) {
                        $router->forceFill([
                            'last_error' => mb_substr($e->getMessage(), 0, 2000),
                        ])->save();
                        $failed++;
                    }
                }
            });

        $this->line("TOTAL={$total} SUCCESS={$success} FAILED={$failed}");
        return self::SUCCESS;
    }
}
