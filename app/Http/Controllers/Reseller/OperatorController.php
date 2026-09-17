<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\NetworkZone;
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

        $zones =
            $this->zones(
                $reseller
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
                        ->with(
                            'zone:id,name,service_type'
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                            'zone_id',
                            'permissions',
                            'is_active',
                            'created_at',
                        ]),

                  'zones' =>
                      $zones,

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

                  'zone_id' => [
                      'required',
                      'integer',
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

          $zone =
              $this->operatorZone(
                  $reseller,
                  (int)
                  $data['zone_id']
              );

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


            'staff_role' =>


                'operator',



            'zone_id' =>


                $zone->id,

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

          $zones =
              $this->zones(
                  $reseller
              );

        return Inertia::render(
            'Reseller/Operators/Edit',
            [
                'operator' =>
                    $operator,

                  'zones' =>
                      $zones,

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

                  'zone_id' => [
                      'required',
                      'integer',
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

          $zone =
              $this->operatorZone(
                  $reseller,
                  (int)
                  $data['zone_id']
              );

          $operator->zone_id =
              $zone->id;

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

    private function zones(

        Reseller $reseller

    ) {

        return NetworkZone::query()

            ->where(

                'reseller_id',

                $reseller->id

            )

            ->where(

                'enabled',

                true

            )

            ->whereIn(

                'service_type',

                [

                    'mac',

                    'hotspot',

                ]

            )

            ->orderBy('service_type')

            ->orderBy('name')

            ->get([

                'id',

                'name',

                'service_type',

            ]);

    }


    private function operatorZone(

        Reseller $reseller,

        int $zoneId

    ): NetworkZone {

        return NetworkZone::query()

            ->whereKey($zoneId)

            ->where(

                'reseller_id',

                $reseller->id

            )

            ->where(

                'enabled',

                true

            )

            ->whereIn(

                'service_type',

                [

                    'mac',

                    'hotspot',

                ]

            )

            ->firstOrFail();

    }


    private function operator(
        Reseller $reseller,
        int $id
    ): User {
        return User::query()
              ->with(
                  'zone:id,name,service_type'
              )
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
