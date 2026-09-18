<?php

namespace App\Http\Controllers;

use App\Models\ResellerPlan;
use App\Models\ResellerRegistrationRequest;
use App\Services\Reseller\PublicResellerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicResellerRegistrationController extends Controller
{
    public function create(
        Request $request
    ): Response {
        $plans =
            ResellerPlan::query()
                ->where(
                    'active',
                    true
                )
                ->orderBy('price')
                ->orderBy(
                    'client_limit'
                )
                ->get()
                ->map(
                    fn (
                        ResellerPlan $plan
                    ): array => [
                        'id' =>
                            $plan->id,

                        'name' =>
                            $plan->name,

                        'code' =>
                            $plan->code,

                        'client_limit' =>
                            $plan
                                ->client_limit,

                        'price' =>
                            (float)
                            $plan->price,

                        'validity_days' =>
                            $plan
                                ->validity_days,

                        'features' =>
                            $plan->features
                            ?? [],

                        'is_free_trial' =>
                            (float)
                            $plan->price
                            <= 0.0001
                            && (int)
                            $plan
                                ->validity_days
                            === 7,
                    ]
                )
                ->values();

        $selectedId =
            (int)
            $request->query(
                'plan',
                0
            );

        $selected =
            $plans->firstWhere(
                'id',
                $selectedId
            );

        if (!$selected) {
            $selected =
                $plans->firstWhere(
                    'is_free_trial',
                    true
                )
                ?? $plans->first();
        }

        return Inertia::render(
            'Public/Register',
            [
                'plans' =>
                    $plans,

                'selectedPlan' =>
                    $selected,
            ]
        );
    }

    public function store(
        Request $request,
        PublicResellerRegistrationService $registrations
    ): RedirectResponse {
        $data =
            $request->validate([
                'plan_id' => [
                    'required',
                    'integer',
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
                ],

                'phone' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:1500',
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:255',
                    'confirmed',
                ],

                'terms' => [
                    'accepted',
                ],
            ]);

        $registration =
            $registrations
                ->register(
                    $data,
                    $request
                );

        return redirect()
            ->route(
                'website.register.status',
                [
                    'token' =>
                        $registration
                            ->public_token,
                ]
            );
    }

    public function status(
        string $token
    ): Response {
        $registration =
            ResellerRegistrationRequest::query()
                ->where(
                    'public_token',
                    $token
                )
                ->firstOrFail();

        return Inertia::render(
            'Public/RegistrationStatus',
            [
                'registration' => [
                    'company_name' =>
                        $registration
                            ->company_name,

                    'owner_name' =>
                        $registration
                            ->owner_name,

                    'plan_name' =>
                        $registration
                            ->plan_name_snapshot,

                    'price' =>
                        (float)
                        $registration
                            ->plan_price_snapshot,

                    'validity_days' =>
                        $registration
                            ->plan_validity_days_snapshot,

                    'client_limit' =>
                        $registration
                            ->client_limit_snapshot,

                    'status' =>
                        $registration
                            ->status,

                    'auto_approved' =>
                        (bool)
                        $registration
                            ->auto_approved,

                    'review_notes' =>
                        $registration
                            ->review_notes,

                    'created_at' =>
                        $registration
                            ->created_at
                            ?->format(
                                'Y-m-d H:i:s'
                            ),
                ],
            ]
        );
    }
}
