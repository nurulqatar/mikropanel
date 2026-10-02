<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageRequest;
use App\Models\NetworkZone;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'Packages/Index',
            [
                'packages' =>
                    Package::query()
                        ->with(
                            'zone:id,name'
                        )
                        ->latest()
                        ->get(),
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Packages/Create',
            [
                'zones' =>
                    $this->availableMacZones(),
            ]
        );
    }

    public function store(
        PackageRequest $request
    ): RedirectResponse {
        Package::create(
            $request->validated()
        );

        return redirect()
            ->route('packages.index')
            ->with(
                'success',
                'Package created successfully.'
            );
    }

    public function show(
        Package $package
    ): RedirectResponse {
        return redirect()
            ->route('packages.index');
    }

    public function edit(
        Package $package
    ): Response {
        return Inertia::render(
            'Packages/Edit',
            [
                'package' =>
                    $package,

                'zones' =>
                    $this->availableMacZones(
                        $package
                    ),
            ]
        );
    }

    public function update(
        PackageRequest $request,
        Package $package
    ): RedirectResponse {
        $package->update(
            $request->validated()
        );

        return redirect()
            ->route('packages.index')
            ->with(
                'success',
                'Package updated successfully.'
            );
    }

    public function destroy(
        Package $package
    ): RedirectResponse {
        $package->delete();

        return back()
            ->with(
                'success',
                'Package deleted.'
            );
    }

    private function availableMacZones(
        ?Package $package = null
    ) {
        $user =
            request()->user();

        $query =
            NetworkZone::query()
                ->where(
                    'service_type',
                    'mac'
                )
                ->where(
                    function ($query) use (
                        $package
                    ): void {
                        $query->where(
                            'enabled',
                            true
                        );

                        if (
                            $package
                                ?->zone_id
                        ) {
                            $query->orWhere(
                                'id',
                                $package->zone_id
                            );
                        }
                    }
                );

        if (
            $user
            && $user->reseller_id
            && !$user->isResellerOwner()
        ) {
            $zoneId =
                $user->isManager()
                    ? (int) (
                        request()
                            ->session()
                            ->get(
                                'network_zone_id'
                            )
                        ?? 0
                    )
                    : (int) (
                        $user->zone_id
                        ?? 0
                    );

            if ($zoneId > 0) {
                $query->where(
                    'id',
                    $zoneId
                );
            } else {
                $query->whereRaw(
                    '1 = 0'
                );
            }
        }

        return $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }
}
