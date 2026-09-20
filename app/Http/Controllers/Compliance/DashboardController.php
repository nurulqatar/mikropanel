<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Network;
use App\Models\Compliance\Router;
use App\Models\Compliance\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request
    ): Response {
        $organization =
            $request->attributes->get(
                'complianceOrganization'
            );

        $user =
            $request->attributes->get(
                'complianceUser'
            );

        $subscriptions =
            Subscription::query()
                ->with('plan')
                ->where(
                    'organization_id',
                    $organization->id
                )
                ->whereIn(
                    'status',
                    [
                        'active',
                        'trial',
                    ]
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereNull(
                                'expires_at'
                            )
                            ->orWhere(
                                'expires_at',
                                '>',
                                now()
                            );
                    }
                )
                ->latest('id')
                ->get();

        return Inertia::render(
            'Compliance/Dashboard',
            [
                'organization' =>
                    $organization,

                'complianceUser' => [
                    'id' =>
                        $user->id,

                    'name' =>
                        $user->name,

                    'role' =>
                        $user->role,
                ],

                'services' => [
                    'logging' =>
                        $subscriptions
                            ->contains(
                                fn ($item) =>
                                    (bool)
                                    $item
                                        ->logging_enabled
                            ),

                    'filtering' =>
                        $subscriptions
                            ->contains(
                                fn ($item) =>
                                    (bool)
                                    $item
                                        ->filtering_enabled
                            ),
                ],

                'subscriptions' =>
                    $subscriptions,

                'stats' => [
                    'networks' =>
                        Network::query()
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->count(),

                    'routers' =>
                        Router::query()
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->count(),

                    'collectors' =>
                        DB::table(
                            'compliance_collectors'
                        )
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->count(),

                    'natMappings' =>
                        DB::table(
                            'compliance_nat_mappings'
                        )
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->count(),

                    'filterRules' =>
                        DB::table(
                            'compliance_filter_rules'
                        )
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->count(),

                    'alerts' =>
                        DB::table(
                            'compliance_alerts'
                        )
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->whereNull(
                                'resolved_at'
                            )
                            ->count(),
                ],
            ]
        );
    }
}
