<?php

namespace App\Http\Controllers\SuperAdmin\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceRentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $plans = DB::table('compliance_plans')
            ->orderBy('id')
            ->get();

        $rentals = DB::table('compliance_rentals as r')
            ->join(
                'compliance_organizations as o',
                'o.id',
                '=',
                'r.organization_id'
            )
            ->join(
                'compliance_plans as p',
                'p.id',
                '=',
                'r.plan_id'
            )
            ->leftJoin(
                'compliance_subscriptions as s',
                's.id',
                '=',
                'r.subscription_id'
            )
            ->select([
                'r.*',
                'o.name as organization_name',
                'o.code as organization_code',
                'o.account_type',
                'p.name as plan_name',
                'p.service_type',
                's.logging_enabled',
                's.filtering_enabled',
                's.status as subscription_status',
            ])
            ->latest('r.id')
            ->limit(500)
            ->get()
            ->map(
                function ($item) {
                    $item->router_count = DB::table(
                        'compliance_routers'
                    )
                        ->where(
                            'organization_id',
                            $item->organization_id
                        )
                        ->count();

                    $item->collector_count = DB::table(
                        'compliance_collectors'
                    )
                        ->where(
                            'organization_id',
                            $item->organization_id
                        )
                        ->count();

                    $item->storage_count = DB::table(
                        'compliance_storage_targets'
                    )
                        ->where(
                            'organization_id',
                            $item->organization_id
                        )
                        ->where('enabled', true)
                        ->count();

                    $item->setup_state =
                        $item->router_count < 1
                            ? 'pending_hardware'
                            : (
                                $item->logging_enabled
                                && $item->collector_count < 1
                                    ? 'collector_pending'
                                    : (
                                        $item->logging_enabled
                                        && $item->storage_count < 1
                                            ? 'storage_pending'
                                            : 'configured'
                                    )
                            );

                    return $item;
                }
            );

        return Inertia::render(
            'SuperAdmin/Compliance/Dashboard',
            [
                'stats' => [
                    'plans' => $plans->count(),
                    'organizations' => DB::table(
                        'compliance_organizations'
                    )->count(),
                    'active_rentals' => DB::table(
                        'compliance_rentals'
                    )
                        ->where('status', 'active')
                        ->count(),
                    'pending_hardware' => $rentals
                        ->where(
                            'setup_state',
                            'pending_hardware'
                        )
                        ->count(),
                ],
                'plans' => $plans,
                'rentals' => $rentals,
                'organizations' => DB::table(
                    'compliance_organizations'
                )
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                        'account_type',
                        'status',
                        'contact_email',
                    ]),
                'resellers' => $this->sourceOptions(
                    'resellers'
                ),
                'hotels' => $this->sourceOptions(
                    'hotels'
                ),
            ]
        );
    }

    public function storePlan(
        Request $request
    ): RedirectResponse {
        return back()->with(
            'error',
            'Edit the three production Compliance plans from this page.'
        );
    }

    public function updatePlan(
        Request $request,
        int $plan,
        ComplianceRentalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'monthly_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'call_for_price' => [
                'required',
                'boolean',
            ],
            'enabled' => [
                'required',
                'boolean',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $service->updatePlan(
            $plan,
            $data
        );

        return back()->with(
            'success',
            'Compliance plan updated.'
        );
    }

    public function rentStandalone(
        Request $request,
        ComplianceRentalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'organization_name' => [
                'required',
                'string',
                'max:200',
            ],
            'organization_code' => [
                'nullable',
                'string',
                'max:100',
            ],
            'owner_name' => [
                'required',
                'string',
                'max:200',
            ],
            'owner_email' => [
                'required',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:10',
                'max:255',
            ],
            'plan_id' => [
                'required',
                'integer',
                'exists:compliance_plans,id',
            ],
            'days' => [
                'required',
                'integer',
                'between:1,3650',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'payment_status' => [
                'required',
                'in:unpaid,paid,partial,waived',
            ],
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        try {
            $service->rentStandalone(
                $data,
                $request->user()?->id
            );
        } catch (Throwable $e) {
            return back()
                ->withErrors([
                    'rental' => $e->getMessage(),
                ])
                ->withInput();
        }

        return back()->with(
            'success',
            'Standalone service rented. Router/NAS can be connected later.'
        );
    }

    public function rentAddon(
        Request $request,
        ComplianceRentalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'source_type' => [
                'required',
                'in:reseller,hotel',
            ],
            'source_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'plan_id' => [
                'required',
                'integer',
                'exists:compliance_plans,id',
            ],
            'days' => [
                'required',
                'integer',
                'between:1,3650',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'payment_status' => [
                'required',
                'in:unpaid,paid,partial,waived',
            ],
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        try {
            $service->rentAddon(
                $data,
                $request->user()?->id
            );
        } catch (Throwable $e) {
            return back()
                ->withErrors([
                    'addon' => $e->getMessage(),
                ])
                ->withInput();
        }

        return back()->with(
            'success',
            'Compliance add-on rented and linked.'
        );
    }

    public function renewRental(
        Request $request,
        int $rental,
        ComplianceRentalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'days' => [
                'required',
                'integer',
                'between:1,3650',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'payment_status' => [
                'required',
                'in:unpaid,paid,partial,waived',
            ],
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $service->renew(
            $rental,
            $data
        );

        return back()->with(
            'success',
            'Rental renewed.'
        );
    }

    public function suspendRental(
        int $rental,
        ComplianceRentalService $service
    ): RedirectResponse {
        $service->suspend($rental);

        return back()->with(
            'success',
            'Rental suspended. Router filtering policy was not removed.'
        );
    }

    public function reactivateRental(
        Request $request,
        int $rental,
        ComplianceRentalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'days' => [
                'nullable',
                'integer',
                'between:1,3650',
            ],
        ]);

        $service->reactivate(
            $rental,
            (int) ($data['days'] ?? 30)
        );

        return back()->with(
            'success',
            'Rental reactivated.'
        );
    }

    public function resetPassword(
        Request $request,
        int $organization,
        ComplianceRentalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
                'min:10',
                'max:255',
            ],
        ]);

        try {
            $service->resetPassword(
                $organization,
                $data['email'],
                $data['password']
            );
        } catch (Throwable $e) {
            return back()->withErrors([
                'password_reset' => $e->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Standalone Compliance password reset.'
        );
    }

    public function storeOrganization(
        Request $request
    ): RedirectResponse {
        return back()->with(
            'error',
            'Use Rent Standalone or Rent Add-on.'
        );
    }

    public function storeSubscription(
        Request $request
    ): RedirectResponse {
        return back()->with(
            'error',
            'Use the rental workflow.'
        );
    }

    public function storeGrant(
        Request $request
    ): RedirectResponse {
        return back()->with(
            'error',
            'Use Rent Add-on.'
        );
    }

    private function sourceOptions(
        string $table
    ): array {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return DB::table($table)
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->map(
                function ($row) use ($table, $columns): array {
                    $label = null;

                    foreach (
                        [
                            'company_name',
                            'name',
                            'owner_name',
                            'code',
                        ] as $column
                    ) {
                        if (
                            in_array(
                                $column,
                                $columns,
                                true
                            )
                            && !empty($row->{$column})
                        ) {
                            $label = $row->{$column};
                            break;
                        }
                    }

                    return [
                        'id' => (int) $row->id,
                        'label' => $label
                            ?: ($table . ' #' . $row->id),
                    ];
                }
            )
            ->values()
            ->all();
    }
}
