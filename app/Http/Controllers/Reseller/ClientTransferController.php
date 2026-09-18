<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTransferRequest;
use App\Models\IpRange;
use App\Models\NetworkZone;
use App\Models\User;
use App\Services\ClientTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientTransferController extends Controller
{
    public function index(
        Request $request
    ): Response|RedirectResponse {
        $user =
            $this->actor(
                $request
            );

        $this->assertView(
            $user
        );

        $contextZoneId =
            $this->contextZoneId(
                $request,
                $user
            );

        if (
            $user->isManager()
            && !$contextZoneId
        ) {
            return redirect()
                ->route(
                    'reseller.zones.index'
                )
                ->with(
                    'error',
                    'Select a MAC Network Zone before opening Client Transfers.'
                );
        }

        $zones =
            NetworkZone::withoutGlobalScopes()
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
                ->orderBy(
                    'name'
                )
                ->get([
                    'id',
                    'name',
                    'code',
                ]);

        $zoneIds =
            $zones
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();

        $transferQuery =
            ClientTransferRequest::query()
                ->where(
                    'reseller_id',
                    $user->reseller_id
                );

        /*
         * Ordinary operator sees only transfers
         * touching their own zone.
         *
         * Manager sees selected zone.
         *
         * Owner sees company-wide history.
         */
        if (
            $user->role !== 'reseller'
        ) {
            $zoneId =
                (int) $contextZoneId;

            $transferQuery->where(
                function ($query) use (
                    $zoneId
                ): void {
                    $query
                        ->where(
                            'source_zone_id',
                            $zoneId
                        )
                        ->orWhere(
                            'target_zone_id',
                            $zoneId
                        );
                }
            );
        }

        $transfers =
            $transferQuery
                ->latest('id')
                ->limit(500)
                ->get();

        $clientIds =
            $transfers
                ->pluck(
                    'client_id'
                )
                ->unique()
                ->values();

        $clientsById =
            Client::withoutGlobalScopes()
                ->whereIn(
                    'id',
                    $clientIds
                )
                ->get([
                    'id',
                    'client_code',
                    'name',
                    'phone',
                    'mac_address',
                    'zone_id',
                    'expiry_date',
                ])
                ->keyBy('id');

        $zonesById =
            $zones->keyBy(
                'id'
            );

        $requestRows =
            $transfers
                ->map(
                    function (
                        ClientTransferRequest $transfer
                    ) use (
                        $user,
                        $contextZoneId,
                        $clientsById,
                        $zonesById
                    ): array {
                        $client =
                            $clientsById->get(
                                $transfer->client_id
                            );

                        $canSource =
                            $user->role
                                === 'reseller'
                            || (
                                (int)
                                $contextZoneId
                                === (int)
                                $transfer
                                    ->source_zone_id
                            );

                        $canTarget =
                            $user->role
                                === 'reseller'
                            || (
                                (int)
                                $contextZoneId
                                === (int)
                                $transfer
                                    ->target_zone_id
                            );

                        return [
                            'id' =>
                                $transfer->id,

                            'client_id' =>
                                $transfer->client_id,

                            'client_code' =>
                                $client?->client_code,

                            'client_name' =>
                                $client?->name
                                ?? 'Transferred Client',

                            'phone' =>
                                $client?->phone,

                            'mac_address' =>
                                $client?->mac_address,

                            'source_zone_id' =>
                                $transfer
                                    ->source_zone_id,

                            'source_zone_name' =>
                                $zonesById
                                    ->get(
                                        $transfer
                                            ->source_zone_id
                                    )
                                    ?->name,

                            'target_zone_id' =>
                                $transfer
                                    ->target_zone_id,

                            'target_zone_name' =>
                                $zonesById
                                    ->get(
                                        $transfer
                                            ->target_zone_id
                                    )
                                    ?->name,

                            'status' =>
                                $transfer->status,

                            'network_status' =>
                                $transfer
                                    ->network_status,

                            'source_ip_address' =>
                                $transfer
                                    ->source_ip_address,

                            'target_ip_address' =>
                                $transfer
                                    ->target_ip_address,

                            'source_service_end_date' =>
                                $transfer
                                    ->source_service_end_date
                                    ?->toDateString(),

                            'request_note' =>
                                $transfer
                                    ->request_note,

                            'decision_note' =>
                                $transfer
                                    ->decision_note,

                            'network_error' =>
                                $transfer
                                    ->network_error,

                            'requested_at' =>
                                $transfer
                                    ->requested_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'approved_at' =>
                                $transfer
                                    ->approved_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'device_count' =>
                                count(
                                    $transfer
                                        ->device_map
                                    ?? []
                                ),

                            'can_approve' =>
                                $transfer->status
                                    === 'pending'
                                && $canTarget,

                            'can_reject' =>
                                $transfer->status
                                    === 'pending'
                                && $canTarget,

                            'can_cancel' =>
                                $transfer->status
                                    === 'pending'
                                && $canSource,
                        ];
                    }
                )
                ->values();

        $clientQuery =
            Client::withoutGlobalScopes()
                ->whereNull(
                    'deleted_at'
                )
                ->whereNull(
                    'parent_client_id'
                )
                ->where(
                    'reseller_id',
                    $user->reseller_id
                )
                ->whereIn(
                    'zone_id',
                    $zoneIds
                );

        if (
            $user->role !== 'reseller'
        ) {
            $clientQuery->where(
                'zone_id',
                $contextZoneId
            );
        }

        $pendingClientIds =
            ClientTransferRequest::query()
                ->where(
                    'reseller_id',
                    $user->reseller_id
                )
                ->where(
                    'status',
                    'pending'
                )
                ->pluck(
                    'client_id'
                );

        if ($pendingClientIds->isNotEmpty()) {
            $clientQuery->whereNotIn(
                'id',
                $pendingClientIds
            );
        }

        $availableClients =
            $clientQuery
                ->orderBy(
                    'name'
                )
                ->limit(3000)
                ->get([
                    'id',
                    'client_code',
                    'name',
                    'phone',
                    'mac_address',
                    'zone_id',
                    'expiry_date',
                ])
                ->map(
                    function (
                        Client $client
                    ) use (
                        $zonesById
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

                            'mac_address' =>
                                $client->mac_address,

                            'zone_id' =>
                                $client->zone_id,

                            'zone_name' =>
                                $zonesById
                                    ->get(
                                        $client
                                            ->zone_id
                                    )
                                    ?->name,

                            'expiry_date' =>
                                $client
                                    ->expiry_date
                                    ?->toDateString(),

                            'device_count' =>
                                1
                                + DB::table(
                                    'clients'
                                )
                                    ->where(
                                        'parent_client_id',
                                        $client->id
                                    )
                                    ->whereNull(
                                        'deleted_at'
                                    )
                                    ->count(),
                        ];
                    }
                )
                ->values();

        $rangeQuery =
            IpRange::withoutGlobalScopes()
                ->where(
                    'reseller_id',
                    $user->reseller_id
                )
                ->where(
                    'enabled',
                    true
                )
                ->whereIn(
                    'zone_id',
                    $zoneIds
                );

        if (
            $user->role !== 'reseller'
        ) {
            $rangeQuery->where(
                'zone_id',
                $contextZoneId
            );
        }

        $ranges =
            $rangeQuery
                ->orderBy(
                    'name'
                )
                ->get([
                    'id',
                    'zone_id',
                    'name',
                    'network',
                    'start_ip',
                    'end_ip',
                ]);

        return Inertia::render(
            'Reseller/Transfers/Index',
            [
                'transfers' =>
                    $requestRows,

                'clients' =>
                    $availableClients,

                'zones' =>
                    $zones,

                'ipRanges' =>
                    $ranges,

                'currentZoneId' =>
                    $contextZoneId,

                'isOwner' =>
                    $user->role
                        === 'reseller',

                'isManager' =>
                    $user->isManager(),
            ]
        );
    }

    public function store(
        Request $request,
        ClientTransferService $service
    ): RedirectResponse {
        $user =
            $this->actor(
                $request
            );

        $this->assertEdit(
            $user
        );

        $data =
            $request->validate([
                'client_id' => [
                    'required',
                    'integer',
                ],

                'target_zone_id' => [
                    'required',
                    'integer',
                ],

                'request_note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $service->requestTransfer(
            $user,
            (int) $data[
                'client_id'
            ],
            (int) $data[
                'target_zone_id'
            ],
            $data[
                'request_note'
            ] ?? null,
            $this->contextZoneId(
                $request,
                $user
            )
        );

        return back()->with(
            'success',
            'Transfer request sent to the destination zone.'
        );
    }

    public function approve(
        Request $request,
        ClientTransferRequest $transfer,
        ClientTransferService $service
    ): RedirectResponse {
        $user =
            $this->actor(
                $request
            );

        $this->assertEdit(
            $user
        );

        $data =
            $request->validate([
                'ip_range_id' => [
                    'required',
                    'integer',
                ],

                'decision_note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $result =
            $service->approve(
                $user,
                (int) $transfer->id,
                (int) $data[
                    'ip_range_id'
                ],
                $data[
                    'decision_note'
                ] ?? null,
                $this->contextZoneId(
                    $request,
                    $user
                ),
                true
            );

        if (
            $result->network_status
            === 'failed'
        ) {
            return back()->with(
                'error',
                'Transfer approved and accounting moved, but destination MikroTik provisioning needs retry. Automatic sync will keep retrying.'
            );
        }

        return back()->with(
            'success',
            'Client transferred successfully. Current-period billing remains in the source zone; future renewals belong to the destination zone.'
        );
    }

    public function reject(
        Request $request,
        ClientTransferRequest $transfer,
        ClientTransferService $service
    ): RedirectResponse {
        $user =
            $this->actor(
                $request
            );

        $this->assertEdit(
            $user
        );

        $data =
            $request->validate([
                'decision_note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $service->reject(
            $user,
            (int) $transfer->id,
            $data[
                'decision_note'
            ] ?? null,
            $this->contextZoneId(
                $request,
                $user
            )
        );

        return back()->with(
            'success',
            'Transfer request rejected.'
        );
    }

    public function cancel(
        Request $request,
        ClientTransferRequest $transfer,
        ClientTransferService $service
    ): RedirectResponse {
        $user =
            $this->actor(
                $request
            );

        $this->assertEdit(
            $user
        );

        $service->cancel(
            $user,
            (int) $transfer->id,
            $this->contextZoneId(
                $request,
                $user
            )
        );

        return back()->with(
            'success',
            'Transfer request cancelled.'
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
            && $user->is_active,
            403
        );

        return $user;
    }

    private function assertView(
        User $user
    ): void {
        if (
            $user->role === 'reseller'
            || $user->isManager()
        ) {
            return;
        }

        abort_unless(
            $user->hasPermission(
                'clients.view'
            ),
            403
        );
    }

    private function assertEdit(
        User $user
    ): void {
        if (
            $user->role === 'reseller'
            || $user->isManager()
        ) {
            return;
        }

        abort_unless(
            $user->hasPermission(
                'clients.edit'
            ),
            403
        );
    }

    private function contextZoneId(
        Request $request,
        User $user
    ): ?int {
        if (
            !$user->isManager()
        ) {
            return $user->role
                === 'reseller'
                    ? null
                    : (
                        $user->zone_id
                            ? (int)
                            $user->zone_id
                            : null
                    );
        }

        $zoneId =
            (int) (
                $request
                    ->session()
                    ->get(
                        'network_zone_id'
                    )
                ?? 0
            );

        if ($zoneId <= 0) {
            return null;
        }

        $valid =
            NetworkZone::withoutGlobalScopes()
                ->whereKey(
                    $zoneId
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
                ->exists();

        return $valid
            ? $zoneId
            : null;
    }
}
