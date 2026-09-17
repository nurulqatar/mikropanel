<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Reseller\ResellerPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    public function index(
        Request $request,
        ResellerPermissionService $permissions
    ): Response {
        $reseller =
            $this->ownerReseller(
                $request
            );

        return Inertia::render(
            'Reseller/Operators/Index',
            [
                'operators' =>
                    User::query()
                        ->where(
                            'reseller_id',
                            $reseller->id
                        )
                        ->where(
                    'role',
                    'operator'
                )
                ->where(
                    'staff_role',
                    'operator'
                )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                            'permissions',
                            'is_active',
                            'created_at',
                        ]),

                'permissionOptions' =>
                    $permissions
                        ->options(),

            ]
        );
    }

    public function store(
        Request $request,
        ResellerPermissionService $permissions
    ): RedirectResponse {
        $reseller =
            $this->ownerReseller(
                $request
            );


        $allowed =
            $permissions
                ->operatorPermissions();

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

                'permissions' => [
                    'array',
                ],

                'permissions.*' => [
                    Rule::in(
                        $allowed
                    ),
                ],
            ]);

        User::create([
            'reseller_id' =>
                $reseller->id,

            'name' =>
                $data['name'],

            'email' =>
                $data['email'],

            'password' =>
                $data['password'],

            'role' =>
                'operator',

            'permissions' =>
                array_values(
                    array_unique(
                        $data[
                            'permissions'
                        ] ?? []
                    )
                ),

            'is_active' =>
                true,

            'is_super_admin' =>
                false,
        ]);

        return back()->with(
            'success',
            'Operator created.'
        );
    }

    public function edit(
        Request $request,
        User $operator,
        ResellerPermissionService $permissions
    ): Response {
        $reseller =
            $this->ownerReseller(
                $request
            );

        $operator =
            $this->operator(
                $reseller,
                $operator->id
            );

        return Inertia::render(
            'Reseller/Operators/Edit',
            [
                'operator' =>
                    $operator,

                'permissionOptions' =>
                    $permissions
                        ->options(),
            ]
        );
    }

    public function update(
        Request $request,
        User $operator,
        ResellerPermissionService $permissions
    ): RedirectResponse {
        $reseller =
            $this->ownerReseller(
                $request
            );

        $operator =
            $this->operator(
                $reseller,
                $operator->id
            );

        $allowed =
            $permissions
                ->operatorPermissions();

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
                    )->ignore(
                        $operator->id
                    ),
                ],

                'password' => [
                    'nullable',
                    'string',
                    'min:8',
                    'confirmed',
                ],

                'permissions' => [
                    'array',
                ],

                'permissions.*' => [
                    Rule::in(
                        $allowed
                    ),
                ],
            ]);

        $operator->name =
            $data['name'];

        $operator->email =
            $data['email'];

        $operator->permissions =
            array_values(
                array_unique(
                    $data[
                        'permissions'
                    ] ?? []
                )
            );

        if (
            !empty(
                $data['password']
            )
        ) {
            $operator->password =
                $data['password'];
        }

        $operator->save();

        return redirect()
            ->route(
                'reseller.operators.index'
            )
            ->with(
                'success',
                'Operator updated.'
            );
    }

    public function toggle(
        Request $request,
        User $operator
    ): RedirectResponse {
        $reseller =
            $this->ownerReseller(
                $request
            );

        $operator =
            $this->operator(
                $reseller,
                $operator->id
            );

        $operator->forceFill([
            'is_active' =>
                !$operator->is_active,
        ])->save();

        return back()->with(
            'success',
            'Operator status updated.'
        );
    }

    public function destroy(
        Request $request,
        User $operator
    ): RedirectResponse {
        $reseller =
            $this->ownerReseller(
                $request
            );

        $operator =
            $this->operator(
                $reseller,
                $operator->id
            );

        $operator->delete();

        return back()->with(
            'success',
            'Operator deleted.'
        );
    }

    private function ownerReseller(
        Request $request
    ): Reseller {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user
                ->isResellerOwner(),
            403
        );

        return Reseller::query()
            ->findOrFail(
                $user->reseller_id
            );
    }

    private function operator(
        Reseller $reseller,
        int $id
    ): User {
        return User::query()
            ->whereKey($id)
            ->where(
                'reseller_id',
                $reseller->id
            )
            ->where(
                    'role',
                    'operator'
                )
                ->where(
                    'staff_role',
                    'operator'
                )
            ->firstOrFail();
    }
}
