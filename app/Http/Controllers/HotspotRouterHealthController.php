<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\Hotspot\HotspotRouterHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HotspotRouterHealthController extends Controller
{
    /*
     * HOTSPOT_ROUTER_HEALTH_V2
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
                    fn ($q) =>
                        $q->where(
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
                fn ($q) =>
                    $q->where(
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

        $routers =
            $query
                ->orderBy('name')
                ->get()
                ->map(
                    fn (Router $router) => [
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
                    ]
                )
                ->values();

        return Inertia::render(
            'Routers/HotspotHealthIndex',
            [
                'routers' =>
                    $routers,
            ]
        );
    }

    public function show(
        Request $request,
        Router $router,
        HotspotRouterHealthService $health
    ): Response {
        $this->authorizeRouter(
            $request,
            $router
        );

        return Inertia::render(
            'Routers/HotspotHealth',
            [
                'router' =>
                    $this->routerPayload(
                        $router
                    ),

                'health' =>
                    $health->snapshot(
                        $router
                    ),

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
        HotspotRouterHealthService $health
    ): JsonResponse {
        $this->authorizeRouter(
            $request,
            $router
        );

        return response()->json([
            'health' =>
                $health->snapshot(
                    $router
                ),

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
        HotspotRouterHealthService $health
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

            return response()->json([
                'success' =>
                    true,

                'message' =>
                    'Existing Hotspot imported: '
                    . $server
                        ->mikrotik_name,

                'health' =>
                    $health
                        ->snapshot(
                            $router
                        ),

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
        HotspotRouterHealthService $health
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
                    $result[
                        'health'
                    ],

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
