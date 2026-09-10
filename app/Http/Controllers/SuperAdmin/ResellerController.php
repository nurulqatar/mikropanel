<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerAuditLog;
use App\Models\ResellerPlan;
use App\Models\User;
use App\Services\Reseller\ResellerSubscriptionService;
use App\Services\Reseller\ResellerUsageService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    public function index(
        Request $request,
        ResellerUsageService $usage
    ): Response {
        $this->superAdmin(
            $request
        );

        $resellers =
            Reseller::query()
                ->with([
                    'owner:id,name,email,is_active',
                    'activeSubscription.plan',
                ])
                ->withCount([
                    'clients',
                    'routers',
                    'users',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(
                    function (
                        Reseller $reseller
                    ) use (
                        $usage
                    ): array {
                        return [
                            'id' =>
                                $reseller->id,

                            'code' =>
                                $reseller->code,

                            'company_name' =>
                                $reseller
                                    ->company_name,

                            'owner_name' =>
                                $reseller
                                    ->owner_name,

                            'email' =>
                                $reseller->email,

                            'phone' =>
                                $reseller->phone,

                            'status' =>
                                $reseller->status,

                            'wallet_balance' =>
                                (float)
                                $reseller
                                    ->wallet_balance,

                            'clients_count' =>
                                $reseller
                                    ->clients_count,

                            'routers_count' =>
                                $reseller
                                    ->routers_count,

                            'users_count' =>
                                $reseller
                                    ->users_count,

                            'plan' =>
                                $reseller
                                    ->activeSubscription
                                    ?->plan
                                    ?->name,

                            'expires_at' =>
                                $reseller
                                    ->activeSubscription
                                    ?->expires_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'usage' =>
                                $usage
                                    ->snapshot(
                                        $reseller
                                    ),
                        ];
                    }
                );

        return Inertia::render(
            'SuperAdmin/Resellers/Index',
            [
                'resellers' =>
                    $resellers,
            ]
        );
    }

    public function create(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        return Inertia::render(
            'SuperAdmin/Resellers/Create',
            [
                'plans' =>
                    ResellerPlan::query()
                        ->where(
                            'active',
                            true
                        )
                        ->orderBy(
                            'client_limit'
                        )
                        ->get(),
            ]
        );
    }

    public function store(
        Request $request,
        ResellerSubscriptionService $subscriptions
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'company_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'owner_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique(
                        'users',
                        'email'
                    ),
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],

                'reseller_plan_id' => [
                    'required',
                    Rule::exists(
                        'reseller_plans',
                        'id'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'active',
                                true
                            )
                    ),
                ],

                'validity_days' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:3650',
                ],

                'client_limit_override' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:1000000',
                ],

                'operator_limit_override' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000',
                ],

                'router_limit_override' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000',
                ],

                'expiry_mode' => [
                    'required',
                    Rule::in([
                        'panel_lock',
                        'read_only',
                        'block_new_clients',
                        'full_suspend',
                    ]),
                ],
            ]);

        $plan =
            ResellerPlan::query()
                ->findOrFail(
                    $data[
                        'reseller_plan_id'
                    ]
                );

        $reseller =
            DB::transaction(
                function () use (
                    $request,
                    $data
                ): Reseller {
                    $reseller =
                        Reseller::create([
                            'code' =>
                                $this
                                    ->uniqueCode(),

                            'company_name' =>
                                $data[
                                    'company_name'
                                ],

                            'owner_name' =>
                                $data[
                                    'owner_name'
                                ],

                            'email' =>
                                $data[
                                    'email'
                                ],

                            'phone' =>
                                $data[
                                    'phone'
                                ] ?? null,

                            'address' =>
                                $data[
                                    'address'
                                ] ?? null,

                            'status' =>
                                'active',

                            'client_limit_override' =>
                                $data[
                                    'client_limit_override'
                                ] ?? null,

                            'operator_limit_override' =>
                                $data[
                                    'operator_limit_override'
                                ] ?? null,

                            'router_limit_override' =>
                                $data[
                                    'router_limit_override'
                                ] ?? null,

                            'expiry_mode' =>
                                $data[
                                    'expiry_mode'
                                ],

                            'timezone' =>
                                'Asia/Qatar',

                            'currency' =>
                                'QAR',

                            'created_by' =>
                                $request
                                    ->user()
                                    ->id,
                        ]);

                    /*
                     * Login remains intentionally
                     * disabled until full tenant
                     * isolation is enabled.
                     */
                    $owner =
                        User::create([
                            'reseller_id' =>
                                $reseller->id,

                            'name' =>
                                $data[
                                    'owner_name'
                                ],

                            'email' =>
                                $data[
                                    'email'
                                ],

                            'password' =>
                                $data[
                                    'password'
                                ],

                            'role' =>
                                'reseller',

                            'permissions' =>
                                [],

                            'is_active' =>
                                false,

                            'is_super_admin' =>
                                false,
                        ]);

                    $reseller->forceFill([
                        'owner_user_id' =>
                            $owner->id,
                    ])->save();

                    return $reseller;
                }
            );

        $subscriptions->start(
            $reseller,
            $plan,
            $request->user()->id,
            $data[
                'validity_days'
            ] ?? null
        );

        $this->audit(
            $request,
            $reseller,
            'reseller.created',
            [
                'plan_id' =>
                    $plan->id,
            ]
        );

        return redirect()
            ->route(
                'superadmin.resellers.show',
                $reseller
            )
            ->with(
                'success',
                'Reseller created. Login remains disabled until tenant isolation is activated.'
            );
    }

    public function show(
        Request $request,
        Reseller $reseller,
        ResellerUsageService $usage
    ): Response {
        $this->superAdmin(
            $request
        );

        $reseller->load([
            'owner:id,name,email,is_active',
            'activeSubscription.plan',
            'subscriptions.plan',
            'walletTransactions' =>
                function ($query) {
                    $query
                        ->latest('id')
                        ->limit(20);
                },
        ]);

        return Inertia::render(
            'SuperAdmin/Resellers/Show',
            [
                'reseller' =>
                    $reseller,

                'usage' =>
                    $usage->snapshot(
                        $reseller
                    ),

                'plans' =>
                    ResellerPlan::query()
                        ->where(
                            'active',
                            true
                        )
                        ->orderBy(
                            'client_limit'
                        )
                        ->get(),
            ]
        );
    }

    public function edit(
        Request $request,
        Reseller $reseller
    ): Response {
        $this->superAdmin(
            $request
        );

        $reseller->load(
            'owner:id,name,email,is_active'
        );

        return Inertia::render(
            'SuperAdmin/Resellers/Edit',
            [
                'reseller' =>
                    $reseller,
            ]
        );
    }

    public function update(
        Request $request,
        Reseller $reseller
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $owner =
            $reseller->owner;

        $data =
            $request->validate([
                'company_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'owner_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique(
                        'users',
                        'email'
                    )->ignore(
                        $owner?->id
                    ),
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'password' => [
                    'nullable',
                    'string',
                    'min:8',
                    'confirmed',
                ],

                'client_limit_override' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:1000000',
                ],

                'operator_limit_override' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000',
                ],

                'router_limit_override' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000',
                ],

                'expiry_mode' => [
                    'required',
                    Rule::in([
                        'panel_lock',
                        'read_only',
                        'block_new_clients',
                        'full_suspend',
                    ]),
                ],
            ]);

        DB::transaction(
            function () use (
                $reseller,
                $owner,
                $data
            ): void {
                $reseller->update([
                    'company_name' =>
                        $data[
                            'company_name'
                        ],

                    'owner_name' =>
                        $data[
                            'owner_name'
                        ],

                    'email' =>
                        $data[
                            'email'
                        ],

                    'phone' =>
                        $data[
                            'phone'
                        ] ?? null,

                    'address' =>
                        $data[
                            'address'
                        ] ?? null,

                    'client_limit_override' =>
                        $data[
                            'client_limit_override'
                        ] ?? null,

                    'operator_limit_override' =>
                        $data[
                            'operator_limit_override'
                        ] ?? null,

                    'router_limit_override' =>
                        $data[
                            'router_limit_override'
                        ] ?? null,

                    'expiry_mode' =>
                        $data[
                            'expiry_mode'
                        ],
                ]);

                if ($owner) {
                    $owner->name =
                        $data[
                            'owner_name'
                        ];

                    $owner->email =
                        $data[
                            'email'
                        ];

                    if (
                        !empty(
                            $data[
                                'password'
                            ]
                        )
                    ) {
                        $owner->password =
                            $data[
                                'password'
                            ];
                    }

                    $owner->save();
                }
            }
        );

        $this->audit(
            $request,
            $reseller,
            'reseller.updated'
        );

        return redirect()
            ->route(
                'superadmin.resellers.show',
                $reseller
            )
            ->with(
                'success',
                'Reseller updated.'
            );
    }

    public function suspend(
        Request $request,
        Reseller $reseller
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

        $reseller->forceFill([
            'status' =>
                'suspended',

            'suspended_at' =>
                Carbon::now(
                    $reseller->timezone
                    ?: 'Asia/Qatar'
                ),

            'suspension_reason' =>
                $data['reason']
                ?? 'Manual suspension',
        ])->save();

        if ($reseller->owner) {
            $reseller
                ->owner
                ->forceFill([
                    'is_active' =>
                        false,
                ])
                ->save();
        }

        $this->audit(
            $request,
            $reseller,
            'reseller.suspended',
            [
                'reason' =>
                    $reseller
                        ->suspension_reason,
            ]
        );

        return back()->with(
            'success',
            'Reseller suspended.'
        );
    }

    public function reactivate(
        Request $request,
        Reseller $reseller
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        /*
         * Owner login remains disabled in R2.
         * We reactivate the reseller business
         * account only.
         */
        $reseller->forceFill([
            'status' =>
                'active',

            'suspended_at' =>
                null,

            'suspension_reason' =>
                null,
        ])->save();

        $this->audit(
            $request,
            $reseller,
            'reseller.reactivated'
        );

        return back()->with(
            'success',
            'Reseller business account reactivated. Portal login remains disabled until tenant isolation is complete.'
        );
    }

    public function renew(
        Request $request,
        Reseller $reseller,
        ResellerSubscriptionService $subscriptions
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'reseller_plan_id' => [
                    'nullable',
                    Rule::exists(
                        'reseller_plans',
                        'id'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'active',
                                true
                            )
                    ),
                ],

                'validity_days' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:3650',
                ],
            ]);

        $plan = null;

        if (
            !empty(
                $data[
                    'reseller_plan_id'
                ]
            )
        ) {
            $plan =
                ResellerPlan::query()
                    ->findOrFail(
                        $data[
                            'reseller_plan_id'
                        ]
                    );
        }

        $subscription =
            $subscriptions->renew(
                $reseller,
                $request->user()->id,
                $plan,
                $data[
                    'validity_days'
                ] ?? null
            );

        $this->audit(
            $request,
            $reseller,
            'subscription.renewed',
            [
                'subscription_id' =>
                    $subscription->id,

                'expires_at' =>
                    $subscription
                        ->expires_at
                        ->toDateTimeString(),
            ]
        );

        return back()->with(
            'success',
            'Reseller subscription renewed.'
        );
    }

    public function changePlan(
        Request $request,
        Reseller $reseller,
        ResellerSubscriptionService $subscriptions
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'reseller_plan_id' => [
                    'required',
                    Rule::exists(
                        'reseller_plans',
                        'id'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'active',
                                true
                            )
                    ),
                ],

                'validity_days' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:3650',
                ],
            ]);

        $plan =
            ResellerPlan::query()
                ->findOrFail(
                    $data[
                        'reseller_plan_id'
                    ]
                );

        $subscription =
            $subscriptions->start(
                $reseller,
                $plan,
                $request->user()->id,
                $data[
                    'validity_days'
                ] ?? null
            );

        $this->audit(
            $request,
            $reseller,
            'subscription.plan_changed',
            [
                'plan_id' =>
                    $plan->id,

                'subscription_id' =>
                    $subscription->id,
            ]
        );

        return back()->with(
            'success',
            'Reseller plan changed.'
        );
    }

    private function uniqueCode(): string
    {
        do {
            $code =
                'RSL-'
                . Str::upper(
                    Str::random(8)
                );
        } while (
            Reseller::withTrashed()
                ->where(
                    'code',
                    $code
                )
                ->exists()
        );

        return $code;
    }

    private function audit(
        Request $request,
        Reseller $reseller,
        string $action,
        array $metadata = []
    ): void {
        ResellerAuditLog::create([
            'reseller_id' =>
                $reseller->id,

            'user_id' =>
                $request
                    ->user()
                    ->id,

            'action' =>
                $action,

            'subject_type' =>
                Reseller::class,

            'subject_id' =>
                $reseller->id,

            'metadata' =>
                $metadata,

            'ip_address' =>
                $request->ip(),
        ]);
    }

    private function superAdmin(
        Request $request
    ): void {
        $user = $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user->isSuperAdmin(),
            403
        );
    }
}
