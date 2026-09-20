<?php

namespace App\Http\Controllers\SuperAdmin\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Organization;
use App\Models\Compliance\Plan;
use App\Models\Compliance\Subscription;
use App\Models\Compliance\User as ComplianceUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'SuperAdmin/Compliance/Dashboard',
            [
                'plans' =>
                    Plan::query()
                        ->latest('id')
                        ->get(),

                'organizations' =>
                    Organization::query()
                        ->withCount([
                            'users',
                            'networks',
                            'routers',
                        ])
                        ->with([
                            'subscriptions' =>
                                fn ($query) =>
                                    $query
                                        ->with('plan')
                                        ->latest('id'),
                        ])
                        ->latest('id')
                        ->get(),

                'stats' => [
                    'organizations' =>
                        Organization::query()
                            ->count(),

                    'loggingSubscriptions' =>
                        Subscription::query()
                            ->where(
                                'logging_enabled',
                                true
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->count(),

                    'filteringSubscriptions' =>
                        Subscription::query()
                            ->where(
                                'filtering_enabled',
                                true
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->count(),

                    'routers' =>
                        DB::table(
                            'compliance_routers'
                        )->count(),
                ],
            ]
        );
    }

    public function storePlan(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'code' => [
                    'required',
                    'string',
                    'max:100',
                    'alpha_dash',
                    'unique:compliance_plans,code',
                ],

                'service_type' => [
                    'required',
                    'in:logging,filtering,bundle',
                ],

                'monthly_price' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'call_for_price' => [
                    'nullable',
                    'boolean',
                ],

                'router_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'retention_days' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'filter_rule_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
            ]);

        Plan::query()
            ->create([
                ...$data,

                'call_for_price' =>
                    (bool) (
                        $data[
                            'call_for_price'
                        ] ?? false
                    ),

                'enabled' =>
                    true,
            ]);

        return back()->with(
            'success',
            'Compliance plan created.'
        );
    }

    public function storeOrganization(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'code' => [
                    'required',
                    'string',
                    'max:100',
                    'alpha_dash',
                    'unique:compliance_organizations,code',
                ],

                'account_type' => [
                    'required',
                    'in:standalone,reseller,hotel,mixed',
                ],

                'legal_profile' => [
                    'required',
                    'in:private_enterprise,service_provider,government_affiliated,custom',
                ],

                'contact_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'contact_email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],

                'contact_phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'owner_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'owner_email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:compliance_users,email',
                ],

                'owner_password' => [
                    'required',
                    'string',
                    'min:10',
                    'max:255',
                ],
            ]);

        DB::transaction(
            function () use ($data): void {
                $organization =
                    Organization::query()
                        ->create([
                            'name' =>
                                $data['name'],

                            'code' =>
                                $data['code'],

                            'account_type' =>
                                $data[
                                    'account_type'
                                ],

                            'legal_profile' =>
                                $data[
                                    'legal_profile'
                                ],

                            'country_code' =>
                                'QA',

                            'timezone' =>
                                'Asia/Qatar',

                            'contact_name' =>
                                $data[
                                    'contact_name'
                                ] ?? null,

                            'contact_email' =>
                                $data[
                                    'contact_email'
                                ] ?? null,

                            'contact_phone' =>
                                $data[
                                    'contact_phone'
                                ] ?? null,

                            'status' =>
                                'active',
                        ]);

                ComplianceUser::query()
                    ->create([
                        'organization_id' =>
                            $organization->id,

                        'name' =>
                            $data[
                                'owner_name'
                            ],

                        'email' =>
                            mb_strtolower(
                                trim(
                                    $data[
                                        'owner_email'
                                    ]
                                )
                            ),

                        'password' =>
                            Hash::make(
                                $data[
                                    'owner_password'
                                ]
                            ),

                        'role' =>
                            'owner',

                        'is_active' =>
                            true,
                    ]);
            }
        );

        return back()->with(
            'success',
            'Compliance organization created.'
        );
    }

    public function storeSubscription(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'organization_id' => [
                    'required',

                    Rule::exists(
                        'compliance_organizations',
                        'id'
                    ),
                ],

                'plan_id' => [
                    'required',

                    Rule::exists(
                        'compliance_plans',
                        'id'
                    ),
                ],

                'starts_at' => [
                    'nullable',
                    'date',
                ],

                'expires_at' => [
                    'nullable',
                    'date',
                ],
            ]);

        $plan =
            Plan::query()
                ->findOrFail(
                    $data['plan_id']
                );

        Subscription::query()
            ->create([
                'organization_id' =>
                    $data[
                        'organization_id'
                    ],

                'plan_id' =>
                    $plan->id,

                'logging_enabled' =>
                    in_array(
                        $plan->service_type,
                        [
                            'logging',
                            'bundle',
                        ],
                        true
                    ),

                'filtering_enabled' =>
                    in_array(
                        $plan->service_type,
                        [
                            'filtering',
                            'bundle',
                        ],
                        true
                    ),

                'billing_cycle' =>
                    'monthly',

                'status' =>
                    'active',

                'starts_at' =>
                    $data[
                        'starts_at'
                    ] ?? now(),

                'expires_at' =>
                    $data[
                        'expires_at'
                    ] ?? null,

                'keep_last_filter_policy_on_expiry'
                    => true,
            ]);

        return back()->with(
            'success',
            'Compliance subscription activated.'
        );
    }

    public function storeGrant(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'organization_id' => [
                    'required',

                    Rule::exists(
                        'compliance_organizations',
                        'id'
                    ),
                ],

                'source_type' => [
                    'required',
                    'in:reseller,hotel',
                ],

                'source_id' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'logging_enabled' => [
                    'nullable',
                    'boolean',
                ],

                'filtering_enabled' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        DB::table(
            'compliance_access_grants'
        )->updateOrInsert(
            [
                'organization_id' =>
                    $data[
                        'organization_id'
                    ],

                'source_type' =>
                    $data[
                        'source_type'
                    ],

                'source_id' =>
                    $data[
                        'source_id'
                    ],
            ],
            [
                'logging_enabled' =>
                    (bool) (
                        $data[
                            'logging_enabled'
                        ] ?? false
                    ),

                'filtering_enabled' =>
                    (bool) (
                        $data[
                            'filtering_enabled'
                        ] ?? false
                    ),

                'is_active' =>
                    true,

                'updated_at' =>
                    now(),

                'created_at' =>
                    now(),
            ]
        );

        return back()->with(
            'success',
            'Existing MikroPanel account link saved.'
        );
    }
}
