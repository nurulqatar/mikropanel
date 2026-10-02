<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientRefund;
use App\Models\Invoice;
use App\Models\IpRange;
use App\Models\NetworkZone;
use App\Models\Package;
use App\Models\Payment;
use App\Services\ClientTransferService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MacClientPosController extends Controller
{
    public function __invoke(
        Request $request
    ): Response|RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user
                && $user->isResellerUser()
                && $user->hasPermission(
                    'clients.view'
                ),
            403
        );

        /*
         * MANAGER_MAC_POS_ZONE_GUARD_V1
         *
         * Managers are intentionally not permanently bound
         * to one zone. They must explicitly select an active
         * MAC Network Zone before entering MAC Client POS.
         */
        if ($user->isManager()) {
            $selectedZoneId =
                (int)
                $request
                    ->session()
                    ->get(
                        'network_zone_id',
                        0
                    );

            $selectedZone =
                $selectedZoneId > 0
                    ? NetworkZone::query()
                        ->whereKey(
                            $selectedZoneId
                        )
                        ->where(
                            'reseller_id',
                            $user->reseller_id
                        )
                        ->where(
                            'service_type',
                            'mac'
                        )
                        ->where(
                            'enabled',
                            true
                        )
                        ->first()
                    : null;

            if (!$selectedZone) {
                $request
                    ->session()
                    ->forget(
                        'network_zone_id'
                    );

                return redirect()
                    ->route(
                        'reseller.zones.index'
                    )
                    ->withErrors([
                        'zone_id' =>
                            'Select an active MAC Network Zone before opening MAC Client POS.',
                    ]);
            }
        }

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

                            'parent_client_id' =>
                                $client
                                    ->parent_client_id,

                            'device_label' =>
                                $client
                                    ->device_label,

                            'name' =>
                                $client->name,

                            'phone' =>
                                $client->phone,

                            'email' =>
                                $client->email,

                            'address' =>
                                $client->address,

                            'qatar_id_number' =>
                                $client->qatar_id_number,
                            'qatar_id_expiry_date' =>
                                $client
                                    ->qatar_id_expiry_date
                                    ?->format('Y-m-d'),
                            'occupation' =>
                                $client->occupation,
                            'passport_number' =>
                                $client->passport_number,
                            'passport_expiry_date' =>
                                $client
                                    ->passport_expiry_date
                                    ?->format('Y-m-d'),
                            'document_serial_number' =>
                                $client->document_serial_number,
                            'residency_type' =>
                                $client->residency_type,
                            'employer' =>
                                $client->employer,
                            'place_of_birth' =>
                                $client->place_of_birth,
                            'identity_type' =>
                                $client->identity_type,

                            'identity_number' =>
                                $client->identity_number,

                            'identity_barcode' =>
                                $client->identity_barcode,

                            'nationality' =>
                                $client->nationality,

                            'date_of_birth' =>
                                $client->date_of_birth
                                    ?->format('Y-m-d'),

                            'gender' =>
                                $client->gender,

                            'document_expiry_date' =>
                                $client->document_expiry_date
                                    ?->format('Y-m-d'),

                            'last_recharge_date' =>
                                $client->last_recharge_date
                                    ?->format('Y-m-d'),

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

                            'zone_id' =>
                                $client
                                    ->zone_id,

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
                ->with(
                    'zone:id,name'
                )
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'zone_id',
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

                        'zone_id' =>
                            $package->zone_id,

                        'zone_name' =>
                            $package->zone
                                ?->name,

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

        $today =
            Carbon::today(
                'Asia/Qatar'
            )->toDateString();

        $todayCollection =
            round(
                (float)
                Payment::query()
                    ->whereDate(
                        'payment_date',
                        $today
                    )
                    ->sum(
                        'amount'
                    ),
                2
            );

        $todayRefund =
            round(
                (float)
                ClientRefund::query()
                    ->whereDate(
                        'refund_date',
                        $today
                    )
                    ->sum(
                        'amount'
                    ),
                2
            );

        /*
         * MAC_POS_LIVE_TODAY_DUE_V2
         *
         * Today Due = outstanding balance that
         * still remains on invoices issued today.
         *
         * If the customer pays the due today,
         * this value immediately decreases.
         */
        $todayDueCreated =
            round(
                (float)
                Invoice::query()
                    ->whereDate(
                        'issue_date',
                        $today
                    )
                    ->whereNotIn(
                        'status',
                        [
                            'cancelled',
                            'refunded',
                        ]
                    )
                    ->whereNull(
                        'service_cancelled_at'
                    )
                    ->sum(
                        'due_amount'
                    ),
                2
            );

        $renewedToday =
            Invoice::query()
                ->whereDate(
                    'issue_date',
                    $today
                )
                ->where(
                    'applies_service_period',
                    true
                )
                ->where(
                    'invoice_no',
                    'not like',
                    'INV-NEW-%'
                )
                ->count();

        $newClientsToday =
            Client::query()
                ->whereNull(
                    'parent_client_id'
                )
                ->whereDate(
                    'created_at',
                    $today
                )
                ->count();

        $recentRefunds =
            ClientRefund::query()
                ->with([
                    'client:id,name,client_code',
                    'invoice:id,invoice_no',
                    'refunder:id,name',
                ])
                ->latest('id')
                ->limit(100)
                ->get()
                ->groupBy(
                    'batch_uuid'
                )
                ->map(
                    function ($rows): array {
                        $first =
                            $rows->first();

                        return [
                            'batch_uuid' =>
                                $first
                                    ->batch_uuid,

                            'date' =>
                                $first
                                    ->refund_date
                                    ?->format(
                                        'Y-m-d'
                                    ),

                            'client_name' =>
                                $first
                                    ->client
                                    ?->name,

                            'client_code' =>
                                $first
                                    ->client
                                    ?->client_code,

                            'invoice_no' =>
                                $first
                                    ->invoice
                                    ?->invoice_no,

                            'amount' =>
                                round(
                                    (float)
                                    $rows->sum(
                                        'amount'
                                    ),
                                    2
                                ),

                            'used_days' =>
                                (int)
                                $first
                                    ->used_days,

                            'reason' =>
                                $first
                                    ->reason,

                            'refunded_by' =>
                                $first
                                    ->refunder
                                    ?->name,

                            'created_at' =>
                                $first
                                    ->created_at
                                    ?->timezone(
                                        'Asia/Qatar'
                                    )
                                    ->format(
                                        'Y-m-d H:i'
                                    ),
                        ];
                    }
                )
                ->values()
                ->take(10)
                ->values();

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

                    'refund' =>
                        $user->hasPermission(
                            'payments.manage'
                        ),

                    'form_fields' =>
                        $user->isResellerOwner()
                        || $user->hasPermission(
                            'settings.manage'
                        ),
                ],

                'recentRefunds' =>
                    $recentRefunds,

                'stats' => [
                    'today_collection' =>
                        $todayCollection,

                    'today_due_created' =>
                        $todayDueCreated,

                    'renewed_today' =>
                        $renewedToday,

                    'new_clients_today' =>
                        $newClientsToday,

                    'today_refund' =>
                        $todayRefund,

                    'net_collection' =>
                        round(
                            $todayCollection
                            - $todayRefund,
                            2
                        ),

                    'current_due' =>
                        round(
                            (float)
                            $clients->sum(
                                'total_due'
                            ),
                            2
                        ),

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

    /*
     * MAC_POS_DEVICE_TRANSFER_CONTROLLER_V2
     */
    public function transferDevice(
        Request $request,
        ClientTransferService $service
    ): RedirectResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->isResellerUser()
            && (
                $user->isResellerOwner()
                || $user->hasPermission(
                    'clients.edit'
                )
            ),
            403
        );

        $data =
            $request->validate([
                'device_id' => [
                    'required',
                    'integer',
                ],

                'target_client_id' => [
                    'required',
                    'integer',
                ],
            ]);

        $contextZoneId =
            null;

        if (
            !$user->isResellerOwner()
        ) {
            $contextZoneId =
                $user->isManager()
                    ? (int)
                        $request
                            ->session()
                            ->get(
                                'network_zone_id',
                                0
                            )
                    : (int) (
                        $user->zone_id
                        ?? 0
                    );

            if (
                $contextZoneId
                <= 0
            ) {
                return back()
                    ->withErrors([
                        'transfer' =>
                            'Select an active MAC Network Zone before transferring a device.',
                    ]);
            }
        }

        $result =
            $service
                ->transferDeviceOwnership(
                    $user,
                    (int)
                    $data['device_id'],
                    (int)
                    $data[
                        'target_client_id'
                    ],
                    $contextZoneId,

                    /*
                     * MAC_POS_DEVICE_TRANSFER_AUDIT_IP_V3B
                     */
                    $request->ip()
);

        $message =
            'Device '
            . $result[
                'device_code'
            ]
            . ' transferred to '
            . $result[
                'target_name'
            ]
            . '.';

        if (
            $result[
                'was_main_device'
            ]
        ) {
            if (
                $result[
                    'promoted_client_id'
                ]
            ) {
                $message .=
                    ' '
                    . $result[
                        'promoted_client_code'
                    ]
                    . ' is now the source customer Main Device.';
            } else {
                $message .=
                    ' Source customer has no remaining device.';
            }
        }

        $message .=
            ' MAC, IP, Router, Package and Expiry were preserved.';

        return back()->with(
            'success',
            $message
        );
    }

}
