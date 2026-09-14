<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\IpRange;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MacClientPosController extends Controller
{
    public function __invoke(
        Request $request
    ): Response {
        $user = $request->user();

        abort_unless(
            $user
                && $user->isResellerUser()
                && $user->hasPermission(
                    'clients.view'
                ),
            403
        );

        $clients =
            Client::query()
                ->with([
                    'package:id,name,price,validity_days',
                    'router:id,name',
                    'ipRange:id,name',
                ])
                ->withSum(
                    [
                        'invoices as total_due' =>
                            function ($query) {
                                $query->where(
                                    'status',
                                    '!=',
                                    'cancelled'
                                );
                            },
                    ],
                    'due_amount'
                )
                ->latest('id')
                ->get()
                ->map(
                    function (
                        Client $client
                    ): array {
                        return [
                            'id' =>
                                $client->id,

                            'client_code' =>
                                $client->client_code,

                            'name' =>
                                $client->name,

                            'phone' =>
                                $client->phone,

                            'email' =>
                                $client->email,

                            'address' =>
                                $client->address,

                            'mac_address' =>
                                $client
                                    ->active_mac_address
                                ?: $client
                                    ->mac_address,

                            'ip_address' =>
                                $client
                                    ->ip_address,

                            'ip_range_id' =>
                                $client
                                    ->ip_range_id,

                            'package_id' =>
                                $client
                                    ->package_id,

                            'expiry_date' =>
                                $client
                                    ->expiry_date
                                    ?->format(
                                        'Y-m-d'
                                    ),

                            'enabled' =>
                                (bool)
                                $client->enabled,

                            'connected' =>
                                (bool)
                                $client->connected,

                            'total_due' =>
                                round(
                                    (float) (
                                        $client
                                            ->total_due
                                        ?? 0
                                    ),
                                    2
                                ),

                            'package' =>
                                $client->package
                                    ? [
                                        'id' =>
                                            $client
                                                ->package
                                                ->id,

                                        'name' =>
                                            $client
                                                ->package
                                                ->name,

                                        'price' =>
                                            (float)
                                            $client
                                                ->package
                                                ->price,

                                        'validity_days' =>
                                            (int)
                                            $client
                                                ->package
                                                ->validity_days,
                                    ]
                                    : null,

                            'router' =>
                                $client->router
                                    ? [
                                        'id' =>
                                            $client
                                                ->router
                                                ->id,

                                        'name' =>
                                            $client
                                                ->router
                                                ->name,
                                    ]
                                    : null,

                            'ip_range' =>
                                $client->ipRange
                                    ? [
                                        'id' =>
                                            $client
                                                ->ipRange
                                                ->id,

                                        'name' =>
                                            $client
                                                ->ipRange
                                                ->name,
                                    ]
                                    : null,
                        ];
                    }
                )
                ->values();

        $packages =
            Package::query()
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'price',
                    'validity_days',
                ])
                ->map(
                    fn (
                        Package $package
                    ): array => [
                        'id' =>
                            $package->id,

                        'name' =>
                            $package->name,

                        'price' =>
                            (float)
                            $package->price,

                        'validity_days' =>
                            (int)
                            $package
                                ->validity_days,
                    ]
                )
                ->values();

        $ipRanges =
            IpRange::query()
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'start_ip',
                    'end_ip',
                ]);

        return Inertia::render(
            'Reseller/MacClientPos',
            [
                'clients' =>
                    $clients,

                'packages' =>
                    $packages,

                'ipRanges' =>
                    $ipRanges,

                'permissions' => [
                    'create' =>
                        $user->hasPermission(
                            'clients.create'
                        ),

                    'edit' =>
                        $user->hasPermission(
                            'clients.edit'
                        ),

                    'renew' =>
                        $user->hasPermission(
                            'clients.renew'
                        ),

                    'suspend' =>
                        $user->hasPermission(
                            'clients.suspend'
                        ),

                    'receive_payment' =>
                        $user->hasPermission(
                            'payments.manage'
                        ),
                ],

                'stats' => [
                    'total' =>
                        $clients->count(),

                    'active' =>
                        $clients
                            ->where(
                                'enabled',
                                true
                            )
                            ->count(),

                    'suspended' =>
                        $clients
                            ->where(
                                'enabled',
                                false
                            )
                            ->count(),

                    'due' =>
                        round(
                            (float)
                            $clients
                                ->sum(
                                    'total_due'
                                ),
                            2
                        ),
                ],
            ]
        );
    }
}
