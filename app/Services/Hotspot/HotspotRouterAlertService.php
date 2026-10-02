<?php

namespace App\Services\Hotspot;

use App\Models\HotspotRouterAlert;
use App\Models\Router;
use Illuminate\Support\Facades\DB;

class HotspotRouterAlertService
{
    /*
     * HOTSPOT_PERSISTENT_ALERTS_V1
     *
     * Health Engine = source of truth.
     * RouterOS WRITE = NONE.
     * MySQL alert lifecycle only.
     */

    public function reconcile(
        Router $router,
        array $snapshot
    ): array {
        $router->loadMissing([
            'zone:id,reseller_id,name,code,service_type',
        ]);

        $issues =
            array_values(
                array_filter(
                    $snapshot[
                        'issues'
                    ] ?? [],
                    fn ($issue) =>
                        trim(
                            (string) (
                                $issue[
                                    'key'
                                ] ?? ''
                            )
                        ) !== ''
                )
            );

        $resellerId =
            $router->zone
                ? $router
                    ->zone
                    ->reseller_id
                : $router
                    ->reseller_id;

        $zoneId =
            $router->zone_id;

        $now =
            now();

        return DB::transaction(
            function () use (
                $router,
                $issues,
                $snapshot,
                $resellerId,
                $zoneId,
                $now
            ): array {
                $currentKeys = [];

                foreach (
                    $issues
                    as $issue
                ) {
                    $key =
                        trim(
                            (string)
                            $issue['key']
                        );

                    if ($key === '') {
                        continue;
                    }

                    $currentKeys[] =
                        $key;

                    $alert =
                        HotspotRouterAlert::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->where(
                                'alert_key',
                                $key
                            )
                            ->lockForUpdate()
                            ->first();

                    if (!$alert) {
                        $alert =
                            new HotspotRouterAlert([
                                'router_id' =>
                                    $router->id,

                                'alert_key' =>
                                    $key,

                                'occurrences' =>
                                    0,

                                'active' =>
                                    false,
                            ]);
                    }

                    $wasActive =
                        (bool)
                        $alert->active;

                    $alert->fill([
                        'reseller_id' =>
                            $resellerId,

                        'zone_id' =>
                            $zoneId,

                        'severity' =>
                            in_array(
                                $issue[
                                    'severity'
                                ] ?? null,
                                [
                                    'critical',
                                    'warning',
                                ],
                                true
                            )
                                ? $issue[
                                    'severity'
                                ]
                                : 'warning',

                        'title' =>
                            (string) (
                                $issue[
                                    'title'
                                ]
                                ?? $key
                            ),

                        'message' =>
                            $issue[
                                'message'
                            ] ?? null,

                        'repairable' =>
                            (bool) (
                                $issue[
                                    'repairable'
                                ] ?? false
                            ),

                        'active' =>
                            true,

                        'last_seen_at' =>
                            $now,

                        'resolved_at' =>
                            null,
                    ]);

                    if (
                        !$alert->exists
                        || !$wasActive
                    ) {
                        $alert
                            ->first_seen_at =
                            $now;
                    }

                    $alert->occurrences =
                        max(
                            0,
                            (int)
                            $alert->occurrences
                        )
                        + 1;

                    $alert->save();
                }

                /*
                 * Only resolve stale alerts after a
                 * successful router health snapshot.
                 *
                 * When router is offline, old problems
                 * stay active because they cannot yet
                 * be verified as recovered.
                 */
                if (
                    $snapshot[
                        'online'
                    ] ?? false
                ) {
                    $query =
                        HotspotRouterAlert::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->where(
                                'active',
                                true
                            );

                    if (
                        $currentKeys !== []
                    ) {
                        $query->whereNotIn(
                            'alert_key',
                            array_values(
                                array_unique(
                                    $currentKeys
                                )
                            )
                        );
                    }

                    $query->update([
                        'active' =>
                            false,

                        'resolved_at' =>
                            $now,
                    ]);
                }

                return [
                    'active' =>
                        HotspotRouterAlert::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->where(
                                'active',
                                true
                            )
                            ->count(),

                    'critical' =>
                        HotspotRouterAlert::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->where(
                                'active',
                                true
                            )
                            ->where(
                                'severity',
                                'critical'
                            )
                            ->count(),

                    'warning' =>
                        HotspotRouterAlert::query()
                            ->where(
                                'router_id',
                                $router->id
                            )
                            ->where(
                                'active',
                                true
                            )
                            ->where(
                                'severity',
                                'warning'
                            )
                            ->count(),
                ];
            }
        );
    }
}
