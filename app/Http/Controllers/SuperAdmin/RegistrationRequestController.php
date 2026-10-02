<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ResellerRegistrationRequest;
use App\Services\Reseller\PublicResellerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationRequestController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        $requests =
            ResellerRegistrationRequest::query()
                ->with([
                    'reviewer:id,name',
                ])
                ->orderByRaw(
                    "CASE
                        WHEN status = 'pending'
                        THEN 0
                        ELSE 1
                    END"
                )
                ->orderByDesc('id')
                ->get()
                ->map(
                    fn (
                        ResellerRegistrationRequest $item
                    ): array => [
                        'id' =>
                            $item->id,

                        'company_name' =>
                            $item
                                ->company_name,

                        'owner_name' =>
                            $item
                                ->owner_name,

                        'email' =>
                            $item->email,

                        'phone' =>
                            $item->phone,

                        'address' =>
                            $item->address,

                        'plan_name' =>
                            $item
                                ->plan_name_snapshot,

                        'price' =>
                            (float)
                            $item
                                ->plan_price_snapshot,

                        'validity_days' =>
                            $item
                                ->plan_validity_days_snapshot,

                        'client_limit' =>
                            $item
                                ->client_limit_snapshot,

                        'status' =>
                            $item->status,

                        'auto_approved' =>
                            (bool)
                            $item
                                ->auto_approved,

                        'review_notes' =>
                            $item
                                ->review_notes,

                        'reviewer' =>
                            $item->reviewer
                                ? [
                                    'id' =>
                                        $item
                                            ->reviewer
                                            ->id,

                                    'name' =>
                                        $item
                                            ->reviewer
                                            ->name,
                                ]
                                : null,

                        'created_at' =>
                            $item
                                ->created_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                        'reviewed_at' =>
                            $item
                                ->reviewed_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),
                    ]
                );

        return Inertia::render(
            'SuperAdmin/RegistrationRequests/Index',
            [
                'requests' =>
                    $requests,

                'stats' => [
                    'pending' =>
                        $requests
                            ->where(
                                'status',
                                'pending'
                            )
                            ->count(),

                    'approved' =>
                        $requests
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'rejected' =>
                        $requests
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),

                    'total' =>
                        $requests->count(),
                ],
            ]
        );
    }

    public function approve(
        Request $request,
        ResellerRegistrationRequest $registration,
        PublicResellerRegistrationService $registrations
    ): RedirectResponse {
        $admin =
            $this->superAdmin(
                $request
            );

        $registrations->approve(
            $registration,
            $admin
        );

        return back()->with(
            'success',
            'Reseller registration approved and subscription activated.'
        );
    }

    public function reject(
        Request $request,
        ResellerRegistrationRequest $registration,
        PublicResellerRegistrationService $registrations
    ): RedirectResponse {
        $admin =
            $this->superAdmin(
                $request
            );

        $data =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $registrations->reject(
            $registration,
            $admin,
            $data['reason']
            ?? null
        );

        return back()->with(
            'success',
            'Reseller registration rejected.'
        );
    }

    private function superAdmin(
        Request $request
    ) {
        $user =
            $request->user();

        abort_unless(
            $user
                && $user->is_active
                && $user
                    ->isSuperAdmin(),
            403
        );

        return $user;
    }
}
