<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\NetworkZone;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NetworkZoneController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $user =
            $this->actor(
                $request
            );

        $zones =
            NetworkZone::query()
                ->where(
                    'reseller_id',
                    $user->reseller_id
                )
                ->orderBy('name')
                ->orderBy('service_type')
                ->get([
                    'id',
                    'name',
                    'service_type',
                    'enabled',
                ]);

        $operators =
            User::query()
                ->where(
                    'reseller_id',
                    $user->reseller_id
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
                    'zone_id',
                    'is_active',
                ]);

        return Inertia::render(
            'Reseller/NetworkZones/Index',
            [
                'zones' =>
                    $zones,

                'operators' =>
                    $operators,

                'currentZoneId' =>
                    $request
                        ->session()
                        ->get(
                            'network_zone_id'
                        ),

                'isOwner' =>
                    $user
                        ->isResellerOwner(),
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
                    'max:120',
                    Rule::unique(
                        'network_zones',
                        'name'
                    )->where(
                        function (
                            $query
                        ) use (
                            $owner,
                            $request
                        ) {
                            return $query
                                ->where(
                                    'reseller_id',
                                    $owner->reseller_id
                                )
                                ->where(
                                    'service_type',
                                    $request->input(
                                        'service_type'
                                    )
                                );
                        }
                    ),
                ],

                'service_type' => [
                    'required',
                    Rule::in([
                        'mac',
                        'hotspot',
                    ]),
                ],

                'enabled' => [
                    'required',
                    'boolean',
                ],
            ]);

        $zone =
            new NetworkZone();

        $zone->forceFill([
            'reseller_id' =>
                $owner->reseller_id,

            'name' =>
                trim(
                    $data['name']
                ),

            'service_type' =>
                $data['service_type'],

            'enabled' =>
                (bool)
                $data['enabled'],
        ]);

        $zone->save();

        return back()->with(
            'success',
            'Network Zone created.'
        );
    }

    public function update(
        Request $request,
        NetworkZone $zone
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $this->ownedZone(
            $owner,
            $zone
        );

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique(
                        'network_zones',
                        'name'
                    )
                        ->ignore(
                            $zone->id
                        )
                        ->where(
                            function (
                                $query
                            ) use (
                                $owner,
                                $request
                            ) {
                                return $query
                                    ->where(
                                        'reseller_id',
                                        $owner->reseller_id
                                    )
                                    ->where(
                                        'service_type',
                                        $request->input(
                                            'service_type'
                                        )
                                    );
                            }
                        ),
                ],

                'service_type' => [
                    'required',
                    Rule::in([
                        'mac',
                        'hotspot',
                    ]),
                ],

                'enabled' => [
                    'required',
                    'boolean',
                ],
            ]);

        if (
            $zone->service_type
                !== $data['service_type']
            && $this->referenced(
                $zone
            )
        ) {
            throw ValidationException::withMessages([
                'service_type' =>
                    'A Network Zone already in use cannot change service type.',
            ]);
        }

        $zone->forceFill([
            'name' =>
                trim(
                    $data['name']
                ),

            'service_type' =>
                $data['service_type'],

            'enabled' =>
                (bool)
                $data['enabled'],
        ])->save();

        if (
            !$zone->enabled
            && (int)
                $request
                    ->session()
                    ->get(
                        'network_zone_id'
                    )
                === (int)
                $zone->id
        ) {
            $request
                ->session()
                ->forget(
                    'network_zone_id'
                );
        }

        return back()->with(
            'success',
            'Network Zone updated.'
        );
    }

    public function select(
        Request $request,
        NetworkZone $zone
    ): RedirectResponse {
        $user =
            $this->actor(
                $request
            );

        $this->ownedZone(
            $user,
            $zone
        );

        if (!$zone->enabled) {
            throw ValidationException::withMessages([
                'zone_id' =>
                    'Only an active Network Zone can be selected.',
            ]);
        }

        $request
            ->session()
            ->put(
                'network_zone_id',
                (int)
                $zone->id
            );

        return back()->with(
            'success',
            'Active Network Zone changed to '
                . $zone->name
                . '.'
        );
    }

    public function assignOperator(
        Request $request,
        User $operator
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        abort_unless(
            (int)
            $operator->reseller_id
                === (int)
                $owner->reseller_id
            && $operator->role
                === 'operator'
            && $operator->staff_role
                === 'operator',
            404
        );

        $data =
            $request->validate([
                'zone_id' => [
                    'required',
                    'integer',
                ],
            ]);

        $zone =
            NetworkZone::query()
                ->where(
                    'id',
                    $data['zone_id']
                )
                ->where(
                    'reseller_id',
                    $owner->reseller_id
                )
                ->where(
                    'enabled',
                    true
                )
                ->firstOrFail();

        $operator->forceFill([
            'zone_id' =>
                $zone->id,

            'staff_role' =>
                'operator',
        ])->save();

        return back()->with(
            'success',
            'Operator assigned to '
                . $zone->name
                . '.'
        );
    }

    public function destroy(
        Request $request,
        NetworkZone $zone
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $this->ownedZone(
            $owner,
            $zone
        );

        if (
            $this->referenced(
                $zone
            )
        ) {
            throw ValidationException::withMessages([
                'zone' =>
                    'This Network Zone is in use and cannot be deleted.',
            ]);
        }

        if (
            (int)
            $request
                ->session()
                ->get(
                    'network_zone_id'
                )
            === (int)
            $zone->id
        ) {
            $request
                ->session()
                ->forget(
                    'network_zone_id'
                );
        }

        $zone->delete();

        return back()->with(
            'success',
            'Network Zone deleted.'
        );
    }

    private function actor(
        Request $request
    ): User {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->reseller_id
            && (
                $user
                    ->isResellerOwner()
                || $user
                    ->isManager()
            ),
            403
        );

        return $user;
    }

    private function owner(
        Request $request
    ): User {
        $user =
            $this->actor(
                $request
            );

        abort_unless(
            $user
                ->isResellerOwner(),
            403
        );

        return $user;
    }

    private function ownedZone(
        User $user,
        NetworkZone $zone
    ): void {
        abort_unless(
            (int)
            $zone->reseller_id
                === (int)
                $user->reseller_id,
            404
        );
    }

    private function referenced(
        NetworkZone $zone
    ): bool {
        foreach (
            [
                'clients',
                'routers',
                'ip_ranges',
                'hotspot_servers',
                'users',
                'invoices',
                'payments',
                'client_refunds',
                'expenses',
                'client_monthly_usages',
            ]
            as $table
        ) {
            if (
                !Schema::hasTable(
                    $table
                )
                || !Schema::hasColumn(
                    $table,
                    'zone_id'
                )
            ) {
                continue;
            }

            if (
                DB::table(
                    $table
                )
                    ->where(
                        'zone_id',
                        $zone->id
                    )
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }
}
