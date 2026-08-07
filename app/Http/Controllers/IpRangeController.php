<?php

namespace App\Http\Controllers;

use App\Http\Requests\IpRangeRequest;
use App\Models\Client;
use App\Models\IpRange;
use Illuminate\Http\RedirectResponse;
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
        $usedIps = Client::query()
            ->whereNotNull('ip_address')
            ->pluck('ip_address')
            ->filter()
            ->unique()
            ->values();

        $ranges = IpRange::query()
            ->latest()
            ->get()
            ->map(function (IpRange $range) use ($usedIps) {
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

    public function create(): Response
    {
        return Inertia::render(
            'IpRanges/Create'
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
        IpRange $ipRange
    ): Response {
        return Inertia::render(
            'IpRanges/Edit',
            [
                'range' => $ipRange,
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
}
