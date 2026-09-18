<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTransferRequest;
use App\Models\IpRange;
use App\Models\NetworkZone;
use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClientTransferService
{
    public function __construct(
        protected IpAllocatorService $allocator,
        protected ClientProvisionService $provision
    ) {
    }

    public function requestTransfer(
        User $actor,
        int $clientId,
        int $targetZoneId,
        ?string $note = null,
        ?int $contextZoneId = null
    ): ClientTransferRequest {
        $this->assertMutationPermission(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $clientId,
                $targetZoneId,
                $note,
                $contextZoneId
            ): ClientTransferRequest {
                $client =
                    $this->rootClient(
                        $clientId,
                        true
                    );

                $this->assertTenant(
                    $actor,
                    (int) $client->reseller_id
                );

                $sourceZone =
                    $this->macZone(
                        (int) $client->zone_id,
                        (int) $client->reseller_id
                    );

                $targetZone =
                    $this->macZone(
                        $targetZoneId,
                        (int) $client->reseller_id
                    );

                if (
                    (int) $sourceZone->id
                    === (int) $targetZone->id
                ) {
                    throw ValidationException::withMessages([
                        'target_zone_id' =>
                            'Source and destination zones cannot be the same.',
                    ]);
                }

                $this->assertZoneAccess(
                    $actor,
                    (int) $sourceZone->id,
                    $contextZoneId
                );

                $pending =
                    ClientTransferRequest::query()
                        ->where(
                            'reseller_id',
                            $client->reseller_id
                        )
                        ->where(
                            'client_id',
                            $client->id
                        )
                        ->where(
                            'status',
                            'pending'
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($pending) {
                    throw ValidationException::withMessages([
                        'client_id' =>
                            'This client already has a pending transfer request.',
                    ]);
                }

                $devices =
                    $this->deviceRows(
                        (int) $client->id,
                        true
                    );

                foreach ($devices as $device) {
                    if (
                        (int) $device->zone_id
                        !== (int) $sourceZone->id
                    ) {
                        throw ValidationException::withMessages([
                            'client_id' =>
                                'All devices must be in the same source zone before transfer.',
                        ]);
                    }
                }

                $deviceMap =
                    $devices
                        ->map(
                            fn ($device) => [
                                'client_id' =>
                                    (int) $device->id,

                                'client_code' =>
                                    $device->client_code,

                                'device_label' =>
                                    $device->device_label,

                                'mac_address' =>
                                    $device->mac_address,

                                'source_zone_id' =>
                                    (int) $device->zone_id,

                                'source_router_id' =>
                                    $device->router_id
                                        ? (int) $device->router_id
                                        : null,

                                'source_ip_range_id' =>
                                    $device->ip_range_id
                                        ? (int) $device->ip_range_id
                                        : null,

                                'source_ip_address' =>
                                    $device->ip_address,
                            ]
                        )
                        ->values()
                        ->all();

                return ClientTransferRequest::create([
                    'reseller_id' =>
                        $client->reseller_id,

                    'client_id' =>
                        $client->id,

                    'source_zone_id' =>
                        $sourceZone->id,

                    'target_zone_id' =>
                        $targetZone->id,

                    'status' =>
                        'pending',

                    'network_status' =>
                        'pending',

                    'requested_by' =>
                        $actor->id,

                    'source_router_id' =>
                        $client->router_id,

                    'source_ip_range_id' =>
                        $client->ip_range_id,

                    'source_ip_address' =>
                        $client->ip_address,

                    /*
                     * Current service period remains
                     * financially owned by source zone.
                     */
                    'source_service_end_date' =>
                        $client->expiry_date
                            ?->toDateString(),

                    'device_map' =>
                        $deviceMap,

                    'request_note' =>
                        $note,

                    'requested_at' =>
                        now(),
                ]);
            },
            3
        );
    }

    public function approve(
        User $actor,
        int $transferId,
        int $targetIpRangeId,
        ?string $decisionNote = null,
        ?int $contextZoneId = null,
        bool $performNetwork = true
    ): ClientTransferRequest {
        $this->assertMutationPermission(
            $actor
        );

        /*
         * First claim / validate the request.
         */
        $claim =
            DB::transaction(
                function () use (
                    $actor,
                    $transferId,
                    $targetIpRangeId,
                    $decisionNote,
                    $contextZoneId
                ): array {
                    $transfer =
                        ClientTransferRequest::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $transferId
                            );

                    if (
                        $transfer->status
                        !== 'pending'
                    ) {
                        throw ValidationException::withMessages([
                            'transfer' =>
                                'Only a pending transfer can be approved.',
                        ]);
                    }

                    $this->assertTenant(
                        $actor,
                        (int) $transfer->reseller_id
                    );

                    $this->assertZoneAccess(
                        $actor,
                        (int) $transfer->target_zone_id,
                        $contextZoneId
                    );

                    $targetZone =
                        $this->macZone(
                            (int) $transfer->target_zone_id,
                            (int) $transfer->reseller_id
                        );

                    $range =
                        IpRange::withoutGlobalScopes()
                            ->whereKey(
                                $targetIpRangeId
                            )
                            ->where(
                                'reseller_id',
                                $transfer->reseller_id
                            )
                            ->where(
                                'zone_id',
                                $targetZone->id
                            )
                            ->where(
                                'enabled',
                                true
                            )
                            ->first();

                    if (!$range) {
                        throw ValidationException::withMessages([
                            'ip_range_id' =>
                                'Select an enabled IP Pool from the destination zone.',
                        ]);
                    }

                    $router =
                        Router::withoutGlobalScopes()
                            ->where(
                                'reseller_id',
                                $transfer->reseller_id
                            )
                            ->where(
                                'zone_id',
                                $targetZone->id
                            )
                            ->where(
                                'enabled',
                                true
                            )
                            ->orderBy(
                                'id'
                            )
                            ->first();

                    if (!$router) {
                        throw ValidationException::withMessages([
                            'transfer' =>
                                'Destination zone has no enabled MikroTik router.',
                        ]);
                    }

                    $root =
                        $this->rootClient(
                            (int) $transfer->client_id,
                            true
                        );

                    if (
                        (int) $root->zone_id
                        !== (int) $transfer->source_zone_id
                    ) {
                        throw ValidationException::withMessages([
                            'transfer' =>
                                'Client is no longer in the original source zone.',
                        ]);
                    }

                    $devices =
                        $this->deviceRows(
                            (int) $root->id,
                            true
                        );

                    foreach ($devices as $device) {
                        if (
                            (int) $device->zone_id
                            !== (int) $transfer->source_zone_id
                        ) {
                            throw ValidationException::withMessages([
                                'transfer' =>
                                    'A client device has already moved to another zone.',
                            ]);
                        }
                    }

                    $transfer->forceFill([
                        'network_status' =>
                            'cleaning',

                        'decision_note' =>
                            $decisionNote,

                        'network_error' =>
                            null,
                    ])->save();

                    return [
                        'transfer_id' =>
                            (int) $transfer->id,

                        'source_zone_id' =>
                            (int) $transfer->source_zone_id,

                        'target_zone_id' =>
                            (int) $transfer->target_zone_id,

                        'target_range_id' =>
                            (int) $range->id,

                        'target_router_id' =>
                            (int) $router->id,

                        'device_ids' =>
                            $devices
                                ->pluck('id')
                                ->map(
                                    fn ($id) =>
                                        (int) $id
                                )
                                ->values()
                                ->all(),
                    ];
                },
                3
            );

        /*
         * Remove old network state BEFORE ownership
         * changes. Approval stops if old source
         * MikroTik entries cannot be cleaned.
         */
        if ($performNetwork) {
            $cleanupErrors = [];

            foreach (
                $claim['device_ids']
                as $deviceId
            ) {
                try {
                    $device =
                        Client::withoutGlobalScopes()
                            ->whereNull(
                                'deleted_at'
                            )
                            ->findOrFail(
                                $deviceId
                            );

                    $ok =
                        $this->provision
                            ->removeFromZone(
                                $device,
                                $claim[
                                    'source_zone_id'
                                ]
                            );

                    if (!$ok) {
                        $cleanupErrors[] =
                            'Client '
                            . $deviceId
                            . ': source router cleanup failed.';
                    }
                } catch (Throwable $exception) {
                    $cleanupErrors[] =
                        'Client '
                        . $deviceId
                        . ': '
                        . $exception
                            ->getMessage();
                }
            }

            if ($cleanupErrors !== []) {
                ClientTransferRequest::query()
                    ->whereKey(
                        $claim[
                            'transfer_id'
                        ]
                    )
                    ->update([
                        'network_status' =>
                            'failed',

                        'network_error' =>
                            implode(
                                ' | ',
                                $cleanupErrors
                            ),

                        'updated_at' =>
                            now(),
                    ]);

                throw ValidationException::withMessages([
                    'transfer' =>
                        'Source MikroTik cleanup failed. Transfer was NOT completed. Retry after fixing router connectivity.',
                ]);
            }
        }

        /*
         * Ownership + IP move is atomic.
         */
        try {
            $move =
                DB::transaction(
                function () use (
                    $actor,
                    $claim,
                    $decisionNote,
                    $performNetwork
                ): array {
                    $transfer =
                        ClientTransferRequest::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $claim[
                                    'transfer_id'
                                ]
                            );

                    if (
                        $transfer->status
                        !== 'pending'
                    ) {
                        throw ValidationException::withMessages([
                            'transfer' =>
                                'Transfer status changed while approval was running.',
                        ]);
                    }

                    /*
                     * Lock the selected pool so two
                     * transfers using this same pool
                     * do not allocate concurrently.
                     */
                    $range =
                        IpRange::withoutGlobalScopes()
                            ->whereKey(
                                $claim[
                                    'target_range_id'
                                ]
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $router =
                        Router::withoutGlobalScopes()
                            ->whereKey(
                                $claim[
                                    'target_router_id'
                                ]
                            )
                            ->where(
                                'enabled',
                                true
                            )
                            ->firstOrFail();

                    $devices =
                        $this->deviceRows(
                            (int) $transfer->client_id,
                            true
                        );

                    $map = [];
                    $movedIds = [];
                    $primaryIp = null;

                    foreach (
                        $devices
                        as $device
                    ) {
                        if (
                            (int) $device->zone_id
                            !== (int) $transfer->source_zone_id
                        ) {
                            throw ValidationException::withMessages([
                                'transfer' =>
                                    'Client zone changed before transfer could finish.',
                            ]);
                        }

                        $newIp =
                            $this->allocator
                                ->allocate(
                                    $range
                                );

                        if (!$newIp) {
                            throw ValidationException::withMessages([
                                'ip_range_id' =>
                                    'Destination IP Pool does not have enough free IP addresses for this client and all devices.',
                            ]);
                        }

                        $old = [
                            'client_id' =>
                                (int) $device->id,

                            'client_code' =>
                                $device->client_code,

                            'device_label' =>
                                $device->device_label,

                            'mac_address' =>
                                $device->mac_address,

                            'source_zone_id' =>
                                (int) $device->zone_id,

                            'source_router_id' =>
                                $device->router_id
                                    ? (int) $device->router_id
                                    : null,

                            'source_ip_range_id' =>
                                $device->ip_range_id
                                    ? (int) $device->ip_range_id
                                    : null,

                            'source_ip_address' =>
                                $device->ip_address,
                        ];

                        DB::table(
                            'clients'
                        )
                            ->where(
                                'id',
                                $device->id
                            )
                            ->update([
                                'zone_id' =>
                                    $transfer
                                        ->target_zone_id,

                                'router_id' =>
                                    $router->id,

                                'ip_range_id' =>
                                    $range->id,

                                'ip_address' =>
                                    $newIp,

                                /*
                                 * New router IDs must be
                                 * discovered during target
                                 * provisioning.
                                 */
                                'mikrotik_lease_id' =>
                                    null,

                                'mikrotik_arp_id' =>
                                    null,

                                'mikrotik_queue_id' =>
                                    null,

                                'connected' =>
                                    false,

                                'updated_at' =>
                                    now(),
                            ]);

                        $map[] =
                            $old
                            + [
                                'target_zone_id' =>
                                    (int)
                                    $transfer
                                        ->target_zone_id,

                                'target_router_id' =>
                                    (int)
                                    $router->id,

                                'target_ip_range_id' =>
                                    (int)
                                    $range->id,

                                'target_ip_address' =>
                                    $newIp,
                            ];

                        $movedIds[] =
                            (int) $device->id;

                        if (
                            (int) $device->id
                            === (int)
                            $transfer->client_id
                        ) {
                            $primaryIp =
                                $newIp;
                        }
                    }

                    $transfer->forceFill([
                        'status' =>
                            'approved',

                        'network_status' =>
                            $performNetwork
                                ? 'provisioning'
                                : 'test_skipped',

                        'approved_by' =>
                            $actor->id,

                        'approved_at' =>
                            now(),

                        'target_router_id' =>
                            $router->id,

                        'target_ip_range_id' =>
                            $range->id,

                        'target_ip_address' =>
                            $primaryIp,

                        'device_map' =>
                            $map,

                        'decision_note' =>
                            $decisionNote,

                        'network_error' =>
                            null,
                    ])->save();

                    return [
                        'transfer_id' =>
                            (int) $transfer->id,

                        'moved_ids' =>
                            $movedIds,
                    ];
                },
                3
            );

        } catch (Throwable $exception) {
            /*
             * TRANSFER_MOVE_FAILURE_RESTORE_V1
             *
             * Source MikroTik cleanup happens before
             * DB ownership changes.
             *
             * If the DB/IP move fails afterwards,
             * the database transaction has rolled
             * the client back to its source zone.
             *
             * Re-provision that source state so a
             * failed transfer cannot leave the
             * customer disconnected.
             */
            if ($performNetwork) {
                $restoreErrors = [];

                foreach (
                    $claim['device_ids']
                    as $deviceId
                ) {
                    try {
                        $device =
                            Client::withoutGlobalScopes()
                                ->whereNull(
                                    'deleted_at'
                                )
                                ->findOrFail(
                                    $deviceId
                                );

                        $this->provision
                            ->provision(
                                $device
                            );

                    } catch (Throwable $restoreException) {
                        $restoreErrors[] =
                            'Client '
                            . $deviceId
                            . ': '
                            . $restoreException
                                ->getMessage();
                    }
                }

                ClientTransferRequest::query()
                    ->whereKey(
                        $claim[
                            'transfer_id'
                        ]
                    )
                    ->update([
                        'network_status' =>
                            $restoreErrors === []
                                ? 'pending'
                                : 'failed',

                        'network_error' =>
                            $restoreErrors === []
                                ? (
                                    'Transfer move failed after source cleanup; '
                                    . 'source network state was restored. '
                                    . $exception->getMessage()
                                )
                                : (
                                    'Transfer move failed and source restoration '
                                    . 'also had errors: '
                                    . implode(
                                        ' | ',
                                        $restoreErrors
                                    )
                                ),

                        'updated_at' =>
                            now(),
                    ]);
            }

            throw $exception;
        }


        if (!$performNetwork) {
            return ClientTransferRequest::query()
                ->findOrFail(
                    $move['transfer_id']
                );
        }

        /*
         * DB ownership is now Zone 2.
         *
         * Provision the same MAC/state to every
         * enabled MikroTik in destination zone.
         *
         * Failure here does NOT roll accounting
         * ownership back. The scheduled client sync
         * can converge destination routers later.
         */
        $provisionErrors = [];

        foreach (
            $move['moved_ids']
            as $deviceId
        ) {
            try {
                $device =
                    Client::withoutGlobalScopes()
                        ->whereNull(
                            'deleted_at'
                        )
                        ->findOrFail(
                            $deviceId
                        );

                $this->provision
                    ->provision(
                        $device
                    );

            } catch (Throwable $exception) {
                $provisionErrors[] =
                    'Client '
                    . $deviceId
                    . ': '
                    . $exception
                        ->getMessage();
            }
        }

        ClientTransferRequest::query()
            ->whereKey(
                $move[
                    'transfer_id'
                ]
            )
            ->update([
                'network_status' =>
                    $provisionErrors === []
                        ? 'synced'
                        : 'failed',

                'network_error' =>
                    $provisionErrors === []
                        ? null
                        : implode(
                            ' | ',
                            $provisionErrors
                        ),

                'updated_at' =>
                    now(),
            ]);

        return ClientTransferRequest::query()
            ->findOrFail(
                $move[
                    'transfer_id'
                ]
            );
    }

    public function reject(
        User $actor,
        int $transferId,
        ?string $note = null,
        ?int $contextZoneId = null
    ): ClientTransferRequest {
        $this->assertMutationPermission(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $transferId,
                $note,
                $contextZoneId
            ): ClientTransferRequest {
                $transfer =
                    ClientTransferRequest::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $transferId
                        );

                if (
                    $transfer->status
                    !== 'pending'
                ) {
                    throw ValidationException::withMessages([
                        'transfer' =>
                            'Only a pending transfer can be rejected.',
                    ]);
                }

                $this->assertTenant(
                    $actor,
                    (int) $transfer->reseller_id
                );

                $this->assertZoneAccess(
                    $actor,
                    (int) $transfer->target_zone_id,
                    $contextZoneId
                );

                $transfer->forceFill([
                    'status' =>
                        'rejected',

                    'network_status' =>
                        'not_required',

                    'rejected_by' =>
                        $actor->id,

                    'rejected_at' =>
                        now(),

                    'decision_note' =>
                        $note,
                ])->save();

                return $transfer;
            },
            3
        );
    }

    public function cancel(
        User $actor,
        int $transferId,
        ?int $contextZoneId = null
    ): ClientTransferRequest {
        $this->assertMutationPermission(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $transferId,
                $contextZoneId
            ): ClientTransferRequest {
                $transfer =
                    ClientTransferRequest::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $transferId
                        );

                if (
                    $transfer->status
                    !== 'pending'
                ) {
                    throw ValidationException::withMessages([
                        'transfer' =>
                            'Only a pending transfer can be cancelled.',
                    ]);
                }

                $this->assertTenant(
                    $actor,
                    (int) $transfer->reseller_id
                );

                $this->assertZoneAccess(
                    $actor,
                    (int) $transfer->source_zone_id,
                    $contextZoneId
                );

                $transfer->forceFill([
                    'status' =>
                        'cancelled',

                    'network_status' =>
                        'not_required',

                    'cancelled_by' =>
                        $actor->id,

                    'cancelled_at' =>
                        now(),
                ])->save();

                return $transfer;
            },
            3
        );
    }

    private function rootClient(
        int $clientId,
        bool $lock = false
    ): Client {
        $query =
            Client::withoutGlobalScopes()
                ->whereNull(
                    'deleted_at'
                );

        if ($lock) {
            $query->lockForUpdate();
        }

        $client =
            $query->findOrFail(
                $clientId
            );

        if ($client->parent_client_id) {
            $query =
                Client::withoutGlobalScopes()
                    ->whereNull(
                        'deleted_at'
                    );

            if ($lock) {
                $query->lockForUpdate();
            }

            $client =
                $query->findOrFail(
                    $client->parent_client_id
                );
        }

        return $client;
    }

    private function deviceRows(
        int $rootClientId,
        bool $lock = false
    ): Collection {
        $query =
            DB::table(
                'clients'
            )
                ->whereNull(
                    'deleted_at'
                )
                ->where(
                    function ($query) use (
                        $rootClientId
                    ): void {
                        $query
                            ->where(
                                'id',
                                $rootClientId
                            )
                            ->orWhere(
                                'parent_client_id',
                                $rootClientId
                            );
                    }
                );

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->get()
            ->sortBy(
                fn ($row) =>
                    (int) $row->id
                    === $rootClientId
                        ? 0
                        : 1
            )
            ->values();
    }

    private function macZone(
        int $zoneId,
        int $resellerId
    ): NetworkZone {
        $zone =
            NetworkZone::withoutGlobalScopes()
                ->whereKey(
                    $zoneId
                )
                ->where(
                    'reseller_id',
                    $resellerId
                )
                ->where(
                    'service_type',
                    'mac'
                )
                ->where(
                    'enabled',
                    true
                )
                ->first();

        if (!$zone) {
            throw ValidationException::withMessages([
                'zone_id' =>
                    'Selected MAC Network Zone is not available.',
            ]);
        }

        return $zone;
    }

    private function assertTenant(
        User $actor,
        int $resellerId
    ): void {
        if (
            !$actor->reseller_id
            || (int) $actor->reseller_id
                !== $resellerId
        ) {
            abort(403);
        }
    }

    private function assertMutationPermission(
        User $actor
    ): void {
        if (
            $actor->role === 'reseller'
            || $actor->isManager()
        ) {
            return;
        }

        abort_unless(
            $actor->hasPermission(
                'clients.edit'
            ),
            403
        );
    }

    private function assertZoneAccess(
        User $actor,
        int $zoneId,
        ?int $contextZoneId
    ): void {
        /*
         * Reseller owner is allowed to operate
         * company-wide.
         */
        if (
            $actor->role === 'reseller'
        ) {
            return;
        }

        $actorZone =
            $actor->isManager()
                ? (int) (
                    $contextZoneId
                    ?? 0
                )
                : (int) (
                    $actor->zone_id
                    ?? 0
                );

        abort_unless(
            $actorZone > 0
            && $actorZone === $zoneId,
            403
        );
    }
}
