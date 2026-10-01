<?php

namespace App\Http\Controllers;

use App\Models\HotspotRouterAlert;
use App\Models\ResellerSetting;
use App\Models\Router;
use App\Services\Hotspot\HotspotRouterAlertService;
use App\Services\Hotspot\HotspotRouterHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HotspotRouterHealthController extends Controller
{
    /*
     * HOTSPOT_ROUTER_HEALTH_V2
     * HOTSPOT_PERSISTENT_ALERTS_V1
     */

    public function index(
        Request $request
    ): Response {
        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        $query =
            Router::query()
                ->with([
                    'zone:id,reseller_id,name,code,service_type',
                ])
                ->whereHas(
                    'zone',
                    fn ($query) =>
                        $query->where(
                            'service_type',
                            'hotspot'
                        )
                );

        $superAdmin =
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin();

        if (!$superAdmin) {
            abort_unless(
                $user->reseller_id,
                403
            );

            $query->whereHas(
                'zone',
                fn ($query) =>
                    $query->where(
                        'reseller_id',
                        $user->reseller_id
                    )
            );

            if (
                method_exists(
                    $user,
                    'isOperator'
                )
                && $user->isOperator()
            ) {
                abort_unless(
                    $user->zone_id,
                    403
                );

                $query->where(
                    'zone_id',
                    $user->zone_id
                );
            }
        }

        $routerModels =
            $query
                ->orderBy(
                    'name'
                )
                ->get();

        /*
         * HOTSPOT_DEVICE_RESET_POLICY_UI_V3
         *
         * Company-scoped operational setting.
         * Operators can view policy but cannot change it.
         */
        $settingsResellerId =
            $user->reseller_id
                ? (int)
                    $user->reseller_id
                : null;

        $resetPolicy =
            $settingsResellerId
                ? [
                    'monthly_limit' =>
                        max(
                            1,
                            min(
                                100,
                                (int)
                                ResellerSetting::getValue(
                                    $settingsResellerId,
                                    'hotspot_device_reset_monthly_limit',
                                    5
                                )
                            )
                        ),

                    'cooldown_minutes' =>
                        max(
                            0,
                            min(
                                1440,
                                (int)
                                ResellerSetting::getValue(
                                    $settingsResellerId,
                                    'hotspot_device_reset_cooldown_minutes',
                                    10
                                )
                            )
                        ),

                    'editable' =>
                        !(
                            method_exists(
                                $user,
                                'isOperator'
                            )
                            && $user
                                ->isOperator()
                        ),
                ]
                : null;

        $routerIds =
            $routerModels
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int)
                        $id
                )
                ->values()
                ->all();

        $activeAlerts =
            $routerIds === []
                ? collect()
                : HotspotRouterAlert::query()
                    ->whereIn(
                        'router_id',
                        $routerIds
                    )
                    ->where(
                        'active',
                        true
                    )
                    ->orderByRaw(
                        "CASE WHEN severity = 'critical' THEN 0 ELSE 1 END"
                    )
                    ->orderByDesc(
                        'last_seen_at'
                    )
                    ->get();

        $alertsByRouter =
            $activeAlerts
                ->groupBy(
                    'router_id'
                );

        $routerNames =
            $routerModels
                ->pluck(
                    'name',
                    'id'
                );

        $routers =
            $routerModels
                ->map(
                    function (
                        Router $router
                    ) use (
                        $alertsByRouter
                    ): array {
                        $routerAlerts =
                            $alertsByRouter
                                ->get(
                                    $router->id,
                                    collect()
                                );

                        return [
                            'id' =>
                                $router->id,

                            'name' =>
                                $router->name,

                            'host' =>
                                $router->host,

                            'enabled' =>
                                (bool)
                                $router->enabled,

                            'connected' =>
                                (bool)
                                $router->connected,

                            'last_checked_at' =>
                                $router
                                    ->last_checked_at
                                    ?->toISOString(),

                            'alert_count' =>
                                $routerAlerts
                                    ->count(),

                            'critical_count' =>
                                $routerAlerts
                                    ->where(
                                        'severity',
                                        'critical'
                                    )
                                    ->count(),

                            'warning_count' =>
                                $routerAlerts
                                    ->where(
                                        'severity',
                                        'warning'
                                    )
                                    ->count(),

                            'zone' => [
                                'id' =>
                                    $router
                                        ->zone
                                        ->id,

                                'name' =>
                                    $router
                                        ->zone
                                        ->name,

                                'code' =>
                                    $router
                                        ->zone
                                        ->code,
                            ],
                        ];
                    }
                )
                ->values();

        return Inertia::render(
            'Routers/HotspotHealthIndex',
            [
                'routers' =>
                    $routers,

                'alertSummary' => [
                    'total' =>
                        $activeAlerts
                            ->count(),

                    'critical' =>
                        $activeAlerts
                            ->where(
                                'severity',
                                'critical'
                            )
                            ->count(),

                    'warning' =>
                        $activeAlerts
                            ->where(
                                'severity',
                                'warning'
                            )
                            ->count(),
                ],

                'resetPolicy' =>
                    $resetPolicy,

                'alerts' =>
                    $activeAlerts
                        ->take(50)
                        ->map(
                            function (
                                HotspotRouterAlert $alert
                            ) use (
                                $routerNames
                            ): array {
                                return [
                                    'id' =>
                                        $alert->id,

                                    'router_id' =>
                                        $alert
                                            ->router_id,

                                    'router_name' =>
                                        $routerNames[
                                            $alert
                                                ->router_id
                                        ]
                                        ?? (
                                            'Router #'
                                            . $alert
                                                ->router_id
                                        ),

                                    'key' =>
                                        $alert
                                            ->alert_key,

                                    'severity' =>
                                        $alert
                                            ->severity,

                                    'title' =>
                                        $alert
                                            ->title,

                                    'message' =>
                                        $alert
                                            ->message,

                                    'repairable' =>
                                        (bool)
                                        $alert
                                            ->repairable,

                                    'occurrences' =>
                                        $alert
                                            ->occurrences,

                                    'first_seen_at' =>
                                        $alert
                                            ->first_seen_at
                                            ?->toISOString(),

                                    'last_seen_at' =>
                                        $alert
                                            ->last_seen_at
                                            ?->toISOString(),
                                ];
                            }
                        )
                        ->values(),
            ]
        );
    }

    public function updateResetPolicy(
        Request $request
    ): RedirectResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->reseller_id,
            403
        );

        if (
            method_exists(
                $user,
                'isOperator'
            )
            && $user->isOperator()
        ) {
            abort(
                403
            );
        }

        $data =
            $request->validate([
                'monthly_limit' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:100',
                ],

                'cooldown_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:1440',
                ],
            ]);

        ResellerSetting::setValue(
            (int)
            $user->reseller_id,
            'hotspot_device_reset_monthly_limit',
            (int)
            $data[
                'monthly_limit'
            ],
            'hotspot',
            'integer'
        );

        ResellerSetting::setValue(
            (int)
            $user->reseller_id,
            'hotspot_device_reset_cooldown_minutes',
            (int)
            $data[
                'cooldown_minutes'
            ],
            'hotspot',
            'integer'
        );

        return back()->with(
            'success',
            'Self Device Reset policy updated.'
        );
    }

    public function show(
        Request $request,
        Router $router,
        HotspotRouterHealthService $health,
        HotspotRouterAlertService $alerts
    ): Response {
        $this->authorizeRouter(
            $request,
            $router
        );

        $snapshot =
            $health->snapshot(
                $router
            );

        $alerts->reconcile(
            $router,
            $snapshot
        );

        return Inertia::render(
            'Routers/HotspotHealth',
            [
                'router' =>
                    $this->routerPayload(
                        $router
                    ),

                'health' =>
                    $snapshot,

                'importCandidate' =>
                    $health
                        ->importCandidate(
                            $router
                        ),
            ]
        );
    }

    public function snapshot(
        Request $request,
        Router $router,
        HotspotRouterHealthService $health,
        HotspotRouterAlertService $alerts
    ): JsonResponse {
        $this->authorizeRouter(
            $request,
            $router
        );

        $snapshot =
            $health->snapshot(
                $router
            );

        $alerts->reconcile(
            $router,
            $snapshot
        );

        return response()->json([
            'health' =>
                $snapshot,

            'importCandidate' =>
                $health
                    ->importCandidate(
                        $router
                    ),
        ]);
    }

    public function live(
        Request $request,
        Router $router,
        HotspotRouterHealthService $health
    ): JsonResponse {
        $this->authorizeRouter(
            $request,
            $router
        );

        return response()->json(
            $health->live(
                $router
            )
        );
    }

    public function import(
        Request $request,
        Router $router,
        HotspotRouterHealthService $health,
        HotspotRouterAlertService $alerts
    ): JsonResponse {
        $this->authorizeRouter(
            $request,
            $router
        );

        try {
            $server =
                $health
                    ->importExisting(
                        $router
                    );

            $snapshot =
                $health->snapshot(
                    $router
                );

            $alerts->reconcile(
                $router,
                $snapshot
            );

            return response()->json([
                'success' =>
                    true,

                'message' =>
                    'Existing Hotspot imported: '
                    . $server
                        ->mikrotik_name,

                'health' =>
                    $snapshot,

                'importCandidate' =>
                    $health
                        ->importCandidate(
                            $router
                        ),
            ]);

        } catch (Throwable $exception) {
            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        $exception
                            ->getMessage(),
                ],
                422
            );
        }
    }

    public function repair(
        Request $request,
        Router $router,
        HotspotRouterHealthService $health,
        HotspotRouterAlertService $alerts
    ): JsonResponse {
        $this->authorizeRouter(
            $request,
            $router
        );

        try {
            $result =
                $health->repair(
                    $router
                );

            $snapshot =
                $result[
                    'health'
                ];

            $alerts->reconcile(
                $router,
                $snapshot
            );

            return response()->json([
                'success' =>
                    true,

                'message' =>
                    (
                        $result[
                            'actions'
                        ] ?? []
                    ) === []
                        ? 'No automatic repair was required.'
                        : 'Repair complete: '
                            . implode(
                                ', ',
                                $result[
                                    'actions'
                                ]
                            ),

                'health' =>
                    $snapshot,

                'importCandidate' =>
                    $health
                        ->importCandidate(
                            $router
                        ),
            ]);

        } catch (Throwable $exception) {
            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        $exception
                            ->getMessage(),
                ],
                422
            );
        }
    }

    protected function authorizeRouter(
        Request $request,
        Router $router
    ): void {
        $router->loadMissing([
            'zone:id,reseller_id,name,code,service_type',
        ]);

        abort_unless(
            $router->zone
            && $router
                ->zone
                ->service_type
                === 'hotspot',
            404
        );

        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        $superAdmin =
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin();

        if ($superAdmin) {
            return;
        }

        abort_unless(
            $router
                ->zone
                ->reseller_id
            && $user->reseller_id
            && (int)
                $router
                    ->zone
                    ->reseller_id
                === (int)
                    $user->reseller_id,
            403
        );

        if (
            method_exists(
                $user,
                'isOperator'
            )
            && $user->isOperator()
        ) {
            abort_unless(
                $user->zone_id
                && (int)
                    $user->zone_id
                    === (int)
                        $router
                            ->zone_id,
                403
            );
        }
    }

    protected function routerPayload(
        Router $router
    ): array {
        return [
            'id' =>
                $router->id,

            'name' =>
                $router->name,

            'host' =>
                $router->host,

            'zone_id' =>
                $router->zone_id,

            'zone' => [
                'id' =>
                    $router
                        ->zone
                        ->id,

                'name' =>
                    $router
                        ->zone
                        ->name,

                'code' =>
                    $router
                        ->zone
                        ->code,
            ],
        ];
    }
}
