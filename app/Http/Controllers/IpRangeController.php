<?php

namespace App\Http\Controllers;

use App\Http\Requests\IpRangeRequest;
use App\Models\Client;
use App\Models\IpRange;
use App\Models\NetworkZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IpRangeController extends Controller
{
    public function index(): Response
    {
        /*
         * Keep this consistent with IpAllocatorService:
         * only non-archived clients reserve an IP.
         */
        /*
         * IP_POOL_ZONE_USAGE_V1
         *
         * The same private subnet may be reused in
         * different remote zones. Usage must therefore
         * be counted independently per zone.
         */
        $usedIpsByZone =
            Client::query()
                ->whereNotNull(
                    'ip_address'
                )
                ->get([
                    'zone_id',
                    'ip_address',
                ])
                ->groupBy(
                    'zone_id'
                )
                ->map(
                    fn ($rows) =>
                        $rows
                            ->pluck(
                                'ip_address'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                );

        $ranges = IpRange::query()
            ->with([
                'zone:id,name,code,service_type',
            ])
            ->latest()
            ->get()
            ->map(function (
                IpRange $range
            ) use (
                $usedIpsByZone
            ) {
                $usedIps =
                    $usedIpsByZone
                        ->get(
                            $range->zone_id,
                            collect()
                        );

                $start = ip2long(
                    $range->start_ip
                );

                $end = ip2long(
                    $range->end_ip
                );

                $total = 0;
                $used = 0;

                if (
                    $start !== false
                    && $end !== false
                    && $end >= $start
                ) {
                    /*
                     * Inclusive:
                     * start_ip and end_ip are both
                     * usable addresses in the pool.
                     */
                    $total =
                        ($end - $start) + 1;

                    foreach ($usedIps as $ip) {
                        $numericIp = ip2long(
                            $ip
                        );

                        if (
                            $numericIp !== false
                            && $numericIp >= $start
                            && $numericIp <= $end
                        ) {
                            $used++;
                        }
                    }
                }

                $free = max(
                    0,
                    $total - $used
                );

                $percentage =
                    $total > 0
                        ? round(
                            ($used / $total) * 100,
                            1
                        )
                        : 0;

                $range->setAttribute(
                    'total_ips',
                    $total
                );

                $range->setAttribute(
                    'used_ips',
                    $used
                );

                $range->setAttribute(
                    'free_ips',
                    $free
                );

                $range->setAttribute(
                    'usage_percent',
                    $percentage
                );

                return $range;
            });

        return Inertia::render(
            'IpRanges/Index',
            [
                'ranges' => $ranges,

                'summary' => [
                    'total_pools' =>
                        $ranges->count(),

                    'enabled_pools' =>
                        $ranges
                            ->where(
                                'enabled',
                                true
                            )
                            ->count(),

                    'total_ips' =>
                        $ranges->sum(
                            'total_ips'
                        ),

                    'used_ips' =>
                        $ranges->sum(
                            'used_ips'
                        ),

                    'free_ips' =>
                        $ranges->sum(
                            'free_ips'
                        ),
                ],
            ]
        );
    }

    public function create(
        Request $request
    ): Response {
        $zones =
            $this->macZoneOptions(
                $request->user()
            );

        return Inertia::render(
            'IpRanges/Create',
            [
                'zones' =>
                    $zones,

                'selectedZoneId' =>
                    $this->preferredMacZoneId(
                        $request,
                        $zones
                    ),
            ]
        );
    }

    public function store(
        IpRangeRequest $request
    ): RedirectResponse {
        IpRange::create(
            $request->validated()
        );

        return redirect()
            ->route('ip-ranges.index')
            ->with(
                'success',
                'IP Pool created successfully.'
            );
    }

    public function edit(
        Request $request,
        IpRange $ipRange
    ): Response {
        $zones =
            $this->macZoneOptions(
                $request->user()
            );

        $ipRange->loadMissing([
            'zone:id,name,code,service_type',
        ]);

        return Inertia::render(
            'IpRanges/Edit',
            [
                'range' =>
                    $ipRange,

                'zones' =>
                    $zones,

                'selectedZoneId' =>
                    $this->preferredMacZoneId(
                        $request,
                        $zones,
                        (int)
                        $ipRange->zone_id
                    ),
            ]
        );
    }

    public function update(
        IpRangeRequest $request,
        IpRange $ipRange
    ): RedirectResponse {
        $ipRange->update(
            $request->validated()
        );

        return redirect()
            ->route('ip-ranges.index')
            ->with(
                'success',
                'IP Pool updated successfully.'
            );
    }

    public function destroy(
        IpRange $ipRange
    ): RedirectResponse {
        if (
            $ipRange
                ->clients()
                ->exists()
        ) {
            return back()->with(
                'error',
                'Cannot delete an IP Pool that is currently used by clients.'
            );
        }

        $ipRange->delete();

        return back()->with(
            'success',
            'IP Pool deleted successfully.'
        );
    }

    /*
     * IP_POOL_MAC_ZONE_OPTIONS_V1
     */
    private function macZoneOptions(
        $user
    ) {
        $query =
            NetworkZone::query()
                ->where(
                    'service_type',
                    'mac'
                )
                ->where(
                    'enabled',
                    true
                );

        if (
            $user
            && method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin()
        ) {
            // All MAC zones.

        } elseif (
            $user
            && $user->reseller_id
        ) {
            $query->where(
                'reseller_id',
                (int)
                $user->reseller_id
            );

            if (
                method_exists(
                    $user,
                    'isOperator'
                )
                && $user->isOperator()
            ) {
                $query->whereKey(
                    (int)
                    $user->zone_id
                );
            }

        } else {
            $query->whereNull(
                'reseller_id'
            );
        }

        return $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'service_type',
            ]);
    }

    private function preferredMacZoneId(
        Request $request,
        $zones,
        ?int $currentZoneId = null
    ): ?int {
        if (
            $currentZoneId
            && $zones->contains(
                'id',
                $currentZoneId
            )
        ) {
            return $currentZoneId;
        }

        $user =
            $request->user();

        if (
            $user
            && method_exists(
                $user,
                'isOperator'
            )
            && $user->isOperator()
            && $user->zone_id
            && $zones->contains(
                'id',
                (int)
                $user->zone_id
            )
        ) {
            return (int)
                $user->zone_id;
        }

        $sessionZoneId =
            (int)
            $request
                ->session()
                ->get(
                    'network_zone_id',
                    0
                );

        if (
            $sessionZoneId
            && $zones->contains(
                'id',
                $sessionZoneId
            )
        ) {
            return $sessionZoneId;
        }

        if (
            $zones->count() === 1
        ) {
            return (int)
                $zones
                    ->first()
                    ->id;
        }

        return null;
    }

}
