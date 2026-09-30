<?php

namespace App\Services\Hotspot;

use App\Models\HotspotServer;
use App\Models\HotspotVoucher;
use App\Models\HotspotVoucherRouterSync;
use App\Models\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotspotZoneVoucherService
{
    public function __construct(
        private readonly HotspotRouterService $routerService
    ) {
    }

    /**
     * Provision one logical voucher to every enabled MikroTik
     * router in the voucher's Network Zone.
     *
     * The legacy hotspot_server_id remains as a compatibility
     * anchor, but zone_id is the ownership boundary.
     */
    public function provisionVoucher(
        HotspotVoucher $voucher
    ): array {
        $voucher->loadMissing([
            'server',
            'plan',
        ]);

        $zoneId = (int) (
            $voucher->zone_id
            ?: $voucher->server?->zone_id
        );

        if (!$zoneId || !$voucher->plan) {
            throw new \RuntimeException(
                'Zone voucher configuration is incomplete.'
            );
        }

        $routers = $this->targetRouters(
            $voucher,
            $zoneId
        );

        if ($routers->isEmpty()) {
            throw new \RuntimeException(
                'No enabled MikroTik router exists in this Hotspot zone.'
            );
        }

        $success = [];
        $failed = [];

        foreach ($routers as $router) {
            $server = $this->serverForRouter(
                $voucher,
                $zoneId,
                $router
            );

            if (!$server) {
                $message =
                    'No enabled Hotspot server was discovered on this router.';

                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    null,
                    'failed',
                    null,
                    $message
                );

                $failed[$router->id] = $message;
                continue;
            }

            if (!$server->connected) {
                $message =
                    'Hotspot server is offline; voucher sync deferred until reconnect.';

                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    $server,
                    'failed',
                    null,
                    $message
                );

                $failed[$router->id] = $message;
                continue;
            }

            try {
                $shadow = clone $voucher;
                $shadow->setAttribute(
                    'hotspot_server_id',
                    $server->id
                );
                $shadow->unsetRelation('server');
                $shadow->unsetRelation('plan');

                $shadowServer = clone $server;
                $shadowServer->setRelation(
                    'router',
                    $router
                );

                /*
                 * One RouterOS user should be usable by every
                 * Hotspot server on this MikroTik. The Network
                 * Zone still limits which MikroTik devices get it.
                 */
                $shadowServer->setAttribute(
                    'mikrotik_name',
                    'all'
                );

                $shadow->setRelation(
                    'server',
                    $shadowServer
                );
                $shadow->setRelation(
                    'plan',
                    $voucher->plan
                );

                $mikrotikId =
                    $this->routerService
                        ->provisionVoucher(
                            $shadow
                        );

                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    $server,
                    'synced',
                    $mikrotikId,
                    null
                );

                $success[$router->id] =
                    $mikrotikId;

            } catch (Throwable $exception) {
                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    $server,
                    'failed',
                    null,
                    $exception->getMessage()
                );

                $failed[$router->id] =
                    $exception->getMessage();

                Log::warning(
                    'Zone voucher sync skipped an unavailable MikroTik.',
                    [
                        'voucher_id' => $voucher->id,
                        'zone_id' => $zoneId,
                        'router_id' => $router->id,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        /*
         * Keep the old single-ID field useful for old screens and
         * convergence logic. It is populated only when every
         * enabled router in the zone is synchronized. Any partial
         * failure leaves it NULL so the existing scheduled retry
         * continues to queue this voucher.
         */
        $complete =
            count($success) === $routers->count();

        $legacyId =
            $complete
                ? (string) reset($success)
                : null;

        $voucher->forceFill([
            'zone_id' => $zoneId,
            'mikrotik_user_id' => $legacyId,
        ])->saveQuietly();

        return [
            'zone_id' => $zoneId,
            'routers' => $routers->count(),
            'synced' => count($success),
            'failed' => count($failed),
            'ids' => $success,
        ];
    }

    public function suspendVoucher(
        HotspotVoucher $voucher
    ): array {
        $voucher->loadMissing('server');

        $zoneId = (int) (
            $voucher->zone_id
            ?: $voucher->server?->zone_id
        );

        if (!$zoneId) {
            return [
                'routers' => 0,
                'suspended' => 0,
                'failed' => 0,
            ];
        }

        $routers = $this->targetRouters(
            $voucher,
            $zoneId
        );

        $suspended = 0;
        $failed = 0;

        foreach ($routers as $router) {
            $server = $this->serverForRouter(
                $voucher,
                $zoneId,
                $router
            );

            if (!$server) {
                $failed++;
                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    null,
                    'failed',
                    null,
                    'No enabled Hotspot server was discovered on this router.'
                );
                continue;
            }

            if (!$server->connected) {
                $failed++;
                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    $server,
                    'failed',
                    null,
                    'Hotspot server is offline; suspension deferred until reconnect.'
                );
                continue;
            }

            try {
                $shadow = clone $voucher;
                $shadow->setAttribute(
                    'hotspot_server_id',
                    $server->id
                );
                $shadow->unsetRelation('server');

                $shadowServer = clone $server;
                $shadowServer->setRelation(
                    'router',
                    $router
                );
                $shadow->setRelation(
                    'server',
                    $shadowServer
                );

                $this->routerService
                    ->suspendVoucher(
                        $shadow
                    );

                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    $server,
                    'suspended',
                    null,
                    null
                );

                $suspended++;

            } catch (Throwable $exception) {
                $failed++;

                $this->mark(
                    $voucher,
                    $zoneId,
                    $router,
                    $server,
                    'failed',
                    null,
                    $exception->getMessage()
                );

                Log::warning(
                    'Zone voucher suspension skipped an unavailable MikroTik.',
                    [
                        'voucher_id' => $voucher->id,
                        'zone_id' => $zoneId,
                        'router_id' => $router->id,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return [
            'routers' => $routers->count(),
            'suspended' => $suspended,
            'failed' => $failed,
        ];
    }

    private function targetRouters(
        HotspotVoucher $voucher,
        int $zoneId
    ): Collection {
        $query = Router::query()
            ->where('zone_id', $zoneId)
            ->where('enabled', true)
            ->orderBy('id');

        if ($voucher->reseller_id) {
            $query->where(
                'reseller_id',
                $voucher->reseller_id
            );
        }

        return $query->get();
    }

    private function serverForRouter(
        HotspotVoucher $voucher,
        int $zoneId,
        Router $router
    ): ?HotspotServer {
        $query = HotspotServer::query()
            ->where('zone_id', $zoneId)
            ->where('router_id', $router->id)
            ->where('enabled', true)
            ->orderByDesc('connected')
            ->orderBy('id');

        if ($voucher->reseller_id) {
            $query->where(
                'reseller_id',
                $voucher->reseller_id
            );
        }

        return $query->first();
    }

    private function mark(
        HotspotVoucher $voucher,
        int $zoneId,
        Router $router,
        ?HotspotServer $server,
        string $status,
        ?string $mikrotikId,
        ?string $error
    ): void {
        HotspotVoucherRouterSync::query()
            ->updateOrCreate(
                [
                    'hotspot_voucher_id' =>
                        $voucher->id,
                    'router_id' =>
                        $router->id,
                ],
                [
                    'reseller_id' =>
                        $voucher->reseller_id,
                    'zone_id' =>
                        $zoneId,
                    'hotspot_server_id' =>
                        $server?->id,
                    'mikrotik_user_id' =>
                        $mikrotikId,
                    'status' =>
                        $status,
                    'last_synced_at' =>
                        now(),
                    'last_error' =>
                        $error,
                ]
            );
    }
}
