<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ResellerPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'Reseller plan created.'
        );
    }

    public function update(
        Request $request,
        ResellerPlan $plan
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $this->validated(
                $request
            );

        $plan->update(
            $data
        );

        return back()->with(
            'success',
            'Reseller plan updated.'
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
            'Reseller plan deleted.'
        );
    }

    private function validated(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'client_limit' => [
                'required',
                'integer',
                'min:1',
                'max:1000000',
            ],

            'operator_limit' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],

            'router_limit' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
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
        $user = $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user->isSuperAdmin(),
            403
        );
    }
}
