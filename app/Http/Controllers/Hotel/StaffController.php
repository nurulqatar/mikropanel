<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelUser;
use App\Services\Hotel\HotelEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public const PERMISSIONS = [
        'guests.manage',
        'vouchers.issue',
        'vouchers.print',
        'sessions.view',
        'reports.view',
    ];

    public function index(): Response
    {
        $admin =
            $this->admin();

        return Inertia::render(
            'Hotel/Staff/Index',
            [
                'staff' =>
                    HotelUser::query()
                        ->where(
                            'hotel_id',
                            $admin->hotel_id
                        )
                        ->orderBy('role')
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                            'role',
                            'permissions',
                            'is_active',
                            'last_login_at',
                        ]),

                'permissionOptions' =>
                    self::PERMISSIONS,
            ]
        );
    }

    public function store(
        Request $request,
        HotelEntitlementService $entitlements
    ): RedirectResponse {
        $admin =
            $this->admin();

        if (
            !$entitlements
                ->canAddReceptionist(
                    $admin->hotel
                )
        ) {
            throw ValidationException::withMessages([
                'staff' =>
                    'Receptionist limit reached or Hotel subscription is inactive.',
            ]);
        }

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique(
                        'hotel_users',
                        'email'
                    ),
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],

                'permissions' => [
                    'nullable',
                    'array',
                ],

                'permissions.*' => [
                    Rule::in(
                        self::PERMISSIONS
                    ),
                ],
            ]);

        HotelUser::query()
            ->create([
                'hotel_id' =>
                    $admin->hotel_id,

                'name' =>
                    trim(
                        $data['name']
                    ),

                'email' =>
                    strtolower(
                        trim(
                            $data['email']
                        )
                    ),

                'password' =>
                    $data['password'],

                'role' =>
                    'receptionist',

                'permissions' =>
                    array_values(
                        array_unique(
                            $data[
                                'permissions'
                            ]
                            ?? []
                        )
                    ),

                'is_active' =>
                    true,
            ]);

        return back()->with(
            'success',
            'Receptionist created.'
        );
    }

    public function update(
        Request $request,
        HotelUser $staff
    ): RedirectResponse {
        $admin =
            $this->admin();

        abort_unless(
            $staff->hotel_id
                === $admin->hotel_id
            && $staff->role
                === 'receptionist',
            404
        );

        $data =
            $request->validate([
                'is_active' => [
                    'required',
                    'boolean',
                ],

                'permissions' => [
                    'nullable',
                    'array',
                ],

                'permissions.*' => [
                    Rule::in(
                        self::PERMISSIONS
                    ),
                ],
            ]);

        $staff->update([
            'is_active' =>
                (bool)
                $data['is_active'],

            'permissions' =>
                array_values(
                    array_unique(
                        $data[
                            'permissions'
                        ]
                        ?? []
                    )
                ),
        ]);

        return back()->with(
            'success',
            'Receptionist updated.'
        );
    }

    public function destroy(
        HotelUser $staff
    ): RedirectResponse {
        $admin =
            $this->admin();

        abort_unless(
            $staff->hotel_id
                === $admin->hotel_id
            && $staff->role
                === 'receptionist',
            404
        );

        $staff->delete();

        return back()->with(
            'success',
            'Receptionist deleted.'
        );
    }

    private function admin(): HotelUser
    {
        $user =
            Auth::guard('hotel')
                ->user();

        abort_unless(
            $user
            && $user->isAdmin(),
            403
        );

        return $user;
    }
}
