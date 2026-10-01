<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\NetworkZone;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Reseller\ResellerModuleService;
use App\Services\Reseller\ResellerPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    public function index(
        Request $request,
        ResellerPermissionService $permissions,
        ResellerModuleService $modules
    ): Response {
        $reseller =
            $this->ownerReseller(
                $request
            );

        $zones =
            $this->zones(
                $reseller,
                $modules
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

                'permissionOptionsByZone' => [
                    'mac' =>
                        $this->permissionOptionsForZone(
                            $reseller,
                            'mac',
                            $permissions,
                            $modules
                        ),

                    'hotspot' =>
                        $this->permissionOptionsForZone(
                            $reseller,
                            'hotspot',
                            $permissions,
                            $modules
                        ),
                ],

            ]
        );
    }

    public function store(
        Request $request,
        ResellerPermissionService $permissions,
        ResellerModuleService $modules
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

        /*
         * OPERATOR_MODULE_ZONE_POLICY_V2
         */
        $this->assertZoneModuleEnabled(
            $reseller,
            $zone,
            $modules
        );

        $selectedPermissions =
            array_values(
                array_unique(
                    $data[
                        'permissions'
                    ] ?? []
                )
            );

        $this->assertPermissionsAllowedForZone(
            $reseller,
            $zone,
            $selectedPermissions,
            $permissions,
            $modules
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
                $selectedPermissions,

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
        ResellerPermissionService $permissions,
        ResellerModuleService $modules
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
                  $reseller,
                  $modules
              );

        return Inertia::render(
            'Reseller/Operators/Edit',
            [
                'operator' =>
                    $operator,

                  'zones' =>
                      $zones,

                'permissionOptionsByZone' => [
                    'mac' =>
                        $this->permissionOptionsForZone(
                            $reseller,
                            'mac',
                            $permissions,
                            $modules
                        ),

                    'hotspot' =>
                        $this->permissionOptionsForZone(
                            $reseller,
                            'hotspot',
                            $permissions,
                            $modules
                        ),
                ],
            ]
        );
    }

    public function update(
        Request $request,
        User $operator,
        ResellerPermissionService $permissions,
        ResellerModuleService $modules
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

        $this->assertZoneModuleEnabled(
            $reseller,
            $zone,
            $modules
        );

        $selectedPermissions =
            array_values(
                array_unique(
                    $data[
                        'permissions'
                    ] ?? []
                )
            );

        $this->assertPermissionsAllowedForZone(
            $reseller,
            $zone,
            $selectedPermissions,
            $permissions,
            $modules
        );


          $operator->zone_id =
              $zone->id;

        $operator->name =
            $data['name'];

        $operator->email =
            $data['email'];

        $operator->permissions =
            $selectedPermissions;

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

    private function permissionOptionsForZone(
        Reseller $reseller,
        string $zoneType,
        ResellerPermissionService $permissions,
        ResellerModuleService $modules
    ): array {
        $zoneModule =
            $this->moduleForZoneType(
                $zoneType
            );

        if (
            !$zoneModule
            || !$modules->enabled(
                (int) $reseller->id,
                $zoneModule
            )
        ) {
            return [];
        }

        $result = [];

        foreach (
            $permissions->options()
            as $permission => $label
        ) {
            $permissionModule =
                $this->moduleForPermission(
                    $permission
                );

            /*
             * null = shared Company permission.
             * Dashboard, Routers, Expenses and
             * Accounting stay available to either
             * enabled service module.
             */
            if ($permissionModule === null) {
                $result[
                    $permission
                ] = $label;

                continue;
            }

            if (
                $permissionModule
                !== $zoneModule
            ) {
                continue;
            }

            $result[
                $permission
            ] = $label;
        }

        return $result;
    }

    private function assertZoneModuleEnabled(
        Reseller $reseller,
        NetworkZone $zone,
        ResellerModuleService $modules
    ): void {
        $module =
            $this->moduleForZoneType(
                (string)
                $zone->service_type
            );

        if (
            !$module
            || !$modules->enabled(
                (int) $reseller->id,
                $module
            )
        ) {
            throw ValidationException::withMessages([
                'zone_id' =>
                    'Selected Network Zone belongs to a disabled Company module.',
            ]);
        }
    }

    private function assertPermissionsAllowedForZone(
        Reseller $reseller,
        NetworkZone $zone,
        array $selected,
        ResellerPermissionService $permissions,
        ResellerModuleService $modules
    ): void {
        $allowed =
            array_keys(
                $this->permissionOptionsForZone(
                    $reseller,
                    (string)
                    $zone->service_type,
                    $permissions,
                    $modules
                )
            );

        $invalid =
            array_values(
                array_diff(
                    $selected,
                    $allowed
                )
            );

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'permissions' =>
                    'One or more permissions are not available for the selected Company module / Network Zone.',
            ]);
        }
    }

    private function moduleForZoneType(
        string $zoneType
    ): ?string {
        return match (
            strtolower(
                trim(
                    $zoneType
                )
            )
        ) {
            'mac' =>
                ResellerModuleService::MAC_CLIENT,

            'hotspot' =>
                ResellerModuleService::HOTSPOT,

            default =>
                null,
        };
    }

    private function moduleForPermission(
        string $permission
    ): ?string {
        foreach ([
            'clients.',
            'packages.',
            'ip_pools.',
            'invoices.',
            'payments.',
        ] as $prefix) {
            if (
                str_starts_with(
                    $permission,
                    $prefix
                )
            ) {
                return
                    ResellerModuleService::MAC_CLIENT;
            }
        }

        if (
            str_starts_with(
                $permission,
                'hotspot.'
            )
        ) {
            return
                ResellerModuleService::HOTSPOT;
        }

        return null;
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
        Reseller $reseller,
        ResellerModuleService $modules
    ) {
        $types = [];

        if (
            $modules->enabled(
                (int) $reseller->id,
                ResellerModuleService::MAC_CLIENT
            )
        ) {
            $types[] = 'mac';
        }

        if (
            $modules->enabled(
                (int) $reseller->id,
                ResellerModuleService::HOTSPOT
            )
        ) {
            $types[] = 'hotspot';
        }

        if ($types === []) {
            return collect();
        }

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
                $types
            )
            ->orderBy(
                'service_type'
            )
            ->orderBy(
                'name'
            )
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
