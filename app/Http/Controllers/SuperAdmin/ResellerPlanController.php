<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ResellerPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResellerPlanController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        return Inertia::render(
            'SuperAdmin/Plans/Index',
            [
                'plans' =>
                    ResellerPlan::query()
                        ->withCount(
                            'subscriptions'
                        )
                        ->orderBy(
                            'is_unlimited'
                        )
                        ->orderBy(
                            'client_limit'
                        )
                        ->get(),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $this->validated(
                $request
            );

        $data['code'] =
            $this->uniqueCode(
                $data['name']
            );

        ResellerPlan::create(
            $data
        );

        return back()->with(
            'success',
            'Company plan created.'
        );
    }

    public function update(
        Request $request,
        ResellerPlan $plan
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $plan->update(
            $this->validated(
                $request
            )
        );

        return back()->with(
            'success',
            'Company plan updated.'
        );
    }

    public function destroy(
        Request $request,
        ResellerPlan $plan
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        if (
            $plan
                ->subscriptions()
                ->exists()
        ) {
            return back()->withErrors([
                'plan' =>
                    'This plan has subscription history. Disable it instead of deleting it.',
            ]);
        }

        $plan->delete();

        return back()->with(
            'success',
            'Company plan deleted.'
        );
    }

    private function validated(
        Request $request
    ): array {
        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'is_unlimited' => [
                    'required',
                    'boolean',
                ],

                'client_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:1000000',
                ],

                'price' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'validity_days' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:3650',
                ],

                'active' => [
                    'required',
                    'boolean',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $data['is_unlimited'] =
            (bool)
            $data['is_unlimited'];

        if (
            !$data['is_unlimited']
            && empty(
                $data['client_limit']
            )
        ) {
            throw ValidationException::withMessages([
                'client_limit' =>
                    'Client Limit is required for a limited plan.',
            ]);
        }

        /*
         * A compatibility sentinel remains in the legacy
         * non-null client_limit column. Runtime enforcement
         * uses is_unlimited and does not enforce this number
         * for Unlimited plans.
         */
        $data['client_limit'] =
            $data['is_unlimited']
                ? 1000000
                : (int)
                    $data['client_limit'];

        /*
         * Operator/router limits are legacy compatibility
         * fields and are not operational Company quotas.
         */
        $data['operator_limit'] = 0;
        $data['router_limit'] = 0;

        return $data;
    }

    private function uniqueCode(
        string $name
    ): string {
        $base =
            Str::upper(
                Str::slug(
                    $name,
                    '-'
                )
            );

        if ($base === '') {
            $base = 'PLAN';
        }

        $code = $base;
        $counter = 1;

        while (
            ResellerPlan::query()
                ->where(
                    'code',
                    $code
                )
                ->exists()
        ) {
            $counter++;

            $code =
                $base
                . '-'
                . $counter;
        }

        return $code;
    }

    private function superAdmin(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user->isSuperAdmin(),
            403
        );
    }
}
