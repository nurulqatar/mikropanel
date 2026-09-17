<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ManagerController extends Controller
{
    private const PERMISSIONS = [
        'dashboard.view',
        'clients.view',
        'routers.view',
        'packages.view',
        'ip_pools.view',
        'invoices.view',
        'invoices.export',
        'payments.view',
        'expenses.view',
        'accounting.view',
        'accounting.export',
        'hotspot.view',
        'hotspot.export',
    ];

    public function index(
        Request $request
    ): Response {
        $owner =
            $this->owner(
                $request
            );

        $managers =
            User::query()
                ->where(
                    'reseller_id',
                    $owner->reseller_id
                )
                ->where(
                    'role',
                    'operator'
                )
                ->where(
                    'staff_role',
                    'manager'
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'created_at',
                ]);

        return Inertia::render(
            'Reseller/Managers/Index',
            [
                'managers' =>
                    $managers,
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

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
                        'users',
                        'email'
                    ),
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ]);

        $manager =
            new User();

        $manager->forceFill([
            'reseller_id' =>
                $owner->reseller_id,

            'zone_id' =>
                null,

            'staff_role' =>
                'manager',

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
                Hash::make(
                    $data['password']
                ),

            'role' =>
                'operator',

            'is_super_admin' =>
                false,

            'permissions' =>
                self::PERMISSIONS,

            'is_active' =>
                true,
        ]);

        $manager->save();

        return back()->with(
            'success',
            'Manager created.'
        );
    }

    public function toggle(
        Request $request,
        User $manager
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $this->manager(
            $owner,
            $manager
        );

        $manager->forceFill([
            'is_active' =>
                !$manager->is_active,
        ])->save();

        return back()->with(
            'success',
            $manager->is_active
                ? 'Manager activated.'
                : 'Manager suspended.'
        );
    }

    public function destroy(
        Request $request,
        User $manager
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $this->manager(
            $owner,
            $manager
        );

        $manager->delete();

        return back()->with(
            'success',
            'Manager deleted.'
        );
    }

    private function owner(
        Request $request
    ): User {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->reseller_id
            && $user
                ->isResellerOwner(),
            403
        );

        return $user;
    }

    private function manager(
        User $owner,
        User $manager
    ): void {
        abort_unless(
            (int)
            $manager->reseller_id
                === (int)
                $owner->reseller_id
            && $manager->role
                === 'operator'
            && $manager->staff_role
                === 'manager',
            404
        );
    }
}
