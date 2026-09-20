<?php

namespace App\Http\Controllers\SuperAdmin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->access(
            $request
        );

        return Inertia::render(
            'SuperAdmin/HotelHotspot/Plans',
            [
                'plans' =>
                    HotelPlan::query()
                        ->withCount(
                            'subscriptions'
                        )
                        ->orderBy('price')
                        ->orderBy('id')
                        ->get(),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->access(
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

        HotelPlan::query()
            ->create(
                $data
            );

        return back()->with(
            'success',
            'Hotel plan created.'
        );
    }

    public function update(
        Request $request,
        HotelPlan $plan
    ): RedirectResponse {
        $this->access(
            $request
        );

        $plan->update(
            $this->validated(
                $request
            )
        );

        return back()->with(
            'success',
            'Hotel plan updated.'
        );
    }

    public function destroy(
        Request $request,
        HotelPlan $plan
    ): RedirectResponse {
        $this->access(
            $request
        );

        if (
            $plan
                ->subscriptions()
                ->exists()
        ) {
            return back()
                ->withErrors([
                    'plan' =>
                        'This plan has subscription history. Disable it instead of deleting it.',
                ]);
        }

        $plan->delete();

        return back()->with(
            'success',
            'Hotel plan deleted.'
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

                'guest_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000000',
                ],

                'is_guest_unlimited' => [
                    'required',
                    'boolean',
                ],

                'router_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000',
                ],

                'is_router_unlimited' => [
                    'required',
                    'boolean',
                ],

                'receptionist_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000',
                ],

                'is_receptionist_unlimited' => [
                    'required',
                    'boolean',
                ],

                'concurrent_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:10000000',
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
                    'max:3000',
                ],
            ]);

        foreach ([
            [
                'is_guest_unlimited',
                'guest_limit',
                'Guest limit',
            ],
            [
                'is_router_unlimited',
                'router_limit',
                'Router limit',
            ],
            [
                'is_receptionist_unlimited',
                'receptionist_limit',
                'Receptionist limit',
            ],
        ] as [
            $unlimited,
            $limit,
            $label,
        ]) {
            $data[$unlimited] =
                (bool)
                $data[$unlimited];

            if (
                !$data[$unlimited]
                && empty(
                    $data[$limit]
                )
            ) {
                throw ValidationException::withMessages([
                    $limit =>
                        "{$label} is required unless Unlimited is enabled.",
                ]);
            }

            if ($data[$unlimited]) {
                $data[$limit] =
                    null;
            }
        }

        $data['active'] =
            (bool)
            $data['active'];

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
            $base =
                'HOTEL-PLAN';
        }

        $code =
            $base;

        $counter = 1;

        while (
            HotelPlan::query()
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

    private function access(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->is_active
            && $request
                ->user()
                ->isSuperAdmin(),
            403
        );
    }
}
