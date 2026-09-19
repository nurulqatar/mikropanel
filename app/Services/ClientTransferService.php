<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTransferRequest;
use App\Models\IpRange;
use App\Models\NetworkZone;
use App\Models\ResellerAuditLog;
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

    /*
     * DEVICE_OWNERSHIP_TRANSFER_V2
     *
     * One physical device can move to another
     * customer inside the SAME Network Zone.
     *
     * Network/service fields stay unchanged:
     * MAC, IP, Router, IP Pool, Package,
     * Client Code, Expiry and MikroTik IDs.
     *
     * MAIN_DEVICE_AUTO_PROMOTE_V2:
     * If Main Device moves, the oldest remaining
     * device automatically becomes Main Device.
     */
    public function transferDeviceOwnership(
        User $actor,
        int $deviceId,
        int $targetClientId,
        ?int $contextZoneId = null,
        ?string $ipAddress = null
): array {
        $this->assertMutationPermission(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $deviceId,
                $targetClientId,
                $contextZoneId,
                    $ipAddress
            ): array {
                $device =
                    Client::withoutGlobalScopes()
                        ->whereNull(
                            'deleted_at'
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            $deviceId
                        );

                $this->assertTenant(
                    $actor,
                    (int)
                    $device->reseller_id
                );

                $sourceRootId =
                    (int) (
                        $device->parent_client_id
                        ?: $device->id
                    );

                if (
                    $sourceRootId
                    === $targetClientId
                ) {
                    throw ValidationException::withMessages([
                        'target_client_id' =>
                            'This device already belongs to the selected customer.',
                    ]);
                }

                $rootIds = [
                    $sourceRootId,
                    $targetClientId,
                ];

                sort(
                    $rootIds,
                    SORT_NUMERIC
                );

                $roots =
                    Client::withoutGlobalScopes()
                        ->whereNull(
                            'deleted_at'
                        )
                        ->whereNull(
                            'parent_client_id'
                        )
                        ->whereIn(
                            'id',
                            $rootIds
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                $source =
                    $roots->get(
                        $sourceRootId
                    );

                $target =
                    $roots->get(
                        $targetClientId
                    );

                if (!$source) {
                    throw ValidationException::withMessages([
                        'device_id' =>
                            'Source customer was not found.',
                    ]);
                }

                if (!$target) {
                    throw ValidationException::withMessages([
                        'target_client_id' =>
                            'Destination customer was not found.',
                    ]);
                }

                if (
                    (int)
                    $source->reseller_id
                    !==
                    (int)
                    $target->reseller_id
                    ||
                    (int)
                    $source->reseller_id
                    !==
                    (int)
                    $device->reseller_id
                ) {
                    throw ValidationException::withMessages([
                        'target_client_id' =>
                            'Device transfer is allowed only inside the same reseller.',
                    ]);
                }

                if (
                    (int)
                    $source->zone_id
                    !==
                    (int)
                    $target->zone_id
                    ||
                    (int)
                    $source->zone_id
                    !==
                    (int)
                    $device->zone_id
                ) {
                    throw ValidationException::withMessages([
                        'target_client_id' =>
                            'Device transfer is allowed only between customers in the same Network Zone.',
                    ]);
                }

                $this->assertZoneAccess(
                    $actor,
                    (int)
                    $source->zone_id,
                    $contextZoneId
                );

                $pendingTransfer =
                    ClientTransferRequest::withoutGlobalScopes()
                        ->where(
                            'reseller_id',
                            $source->reseller_id
                        )
                        ->where(
                            'status',
                            'pending'
                        )
                        ->whereIn(
                            'client_id',
                            [
                                $source->id,
                                $target->id,
                            ]
                        )
                        ->lockForUpdate()
                        ->first();

                if ($pendingTransfer) {
                    throw ValidationException::withMessages([
                        'device_id' =>
                            'Complete or cancel the pending Client Zone Transfer first.',
                    ]);
                }

                /*
                 * Lock both families in deterministic
                 * primary-client order.
                 */
                $familyIds = [
                    (int)
                    $source->id,

                    (int)
                    $target->id,
                ];

                sort(
                    $familyIds,
                    SORT_NUMERIC
                );

                $families = [];

                foreach (
                    $familyIds
                    as $familyId
                ) {
                    $families[
                        $familyId
                    ] =
                        $this->deviceRows(
                            $familyId,
                            true
                        );
                }

                $sourceDevices =
                    $families[
                        (int)
                        $source->id
                    ];

                $targetDevices =
                    $families[
                        (int)
                        $target->id
                    ];

                $sourceIds =
                    $sourceDevices
                        ->pluck('id')
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->values();

                $targetIds =
                    $targetDevices
                        ->pluck('id')
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->values();

                if (
                    !$sourceIds
                        ->contains(
                            (int)
                            $device->id
                        )
                ) {
                    throw ValidationException::withMessages([
                        'device_id' =>
                            'The selected device no longer belongs to this customer.',
                    ]);
                }

                /*
                 * DEVICE_TRANSFER_ACCOUNT_DUE_LOCK_V2
                 *
                 * Do not allow moving a device while
                 * either account has outstanding due.
                 */
                $sourceDueRows =
                    DB::table(
                        'invoices'
                    )
                        ->whereIn(
                            'client_id',
                            $sourceIds->all()
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
                        ->where(
                            'due_amount',
                            '>',
                            0
                        )
                        ->orderBy(
                            'client_id'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get([
                            'id',
                            'client_id',
                            'due_amount',
                        ]);

                $targetDueRows =
                    DB::table(
                        'invoices'
                    )
                        ->whereIn(
                            'client_id',
                            $targetIds->all()
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
                        ->where(
                            'due_amount',
                            '>',
                            0
                        )
                        ->orderBy(
                            'client_id'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get([
                            'id',
                            'client_id',
                            'due_amount',
                        ]);

                $sourceDue =
                    round(
                        (float)
                        $sourceDueRows
                            ->sum(
                                'due_amount'
                            ),
                        2
                    );

                $targetDue =
                    round(
                        (float)
                        $targetDueRows
                            ->sum(
                                'due_amount'
                            ),
                        2
                    );

                if ($sourceDue > 0) {
                    throw ValidationException::withMessages([
                        'device_id' =>
                            'Source customer has outstanding due. Clear all account due before transferring a device.',
                    ]);
                }

                if ($targetDue > 0) {
                    throw ValidationException::withMessages([
                        'target_client_id' =>
                            'Destination customer has outstanding due. Clear all account due before receiving a device.',
                    ]);
                }

                /*
                 * Family refund hard-lock cannot be
                 * escaped through ownership transfer.
                 */
                $allIds =
                    array_values(
                        array_unique(
                            array_merge(
                                $sourceIds
                                    ->all(),
                                $targetIds
                                    ->all()
                            )
                        )
                    );

                $refundLock =
                    DB::table(
                        'client_refunds'
                    )
                        ->whereIn(
                            'client_id',
                            $allIds
                        )
                        ->where(
                            'reason',
                            'like',
                            '%[ACCOUNT_DUE_FAMILY_LOCK_V1]%'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->lockForUpdate()
                        ->first([
                            'id',
                        ]);

                if ($refundLock) {
                    throw ValidationException::withMessages([
                        'device_id' =>
                            'Device transfer is blocked because the source or destination account has an account refund lock.',
                    ]);
                }

                $isMainDevice =
                    (int)
                    $device->id
                    ===
                    (int)
                    $source->id;

                /*
                 * Customer identity fields only.
                 *
                 * Network/service fields intentionally
                 * remain attached to the physical device.
                 */
                $identityFields = [
                    'name',
                    'phone',
                    'email',
                    'address',
                    'identity_type',
                    'identity_number',
                    'identity_barcode',
                    'nationality',
                    'date_of_birth',
                    'gender',
                    'document_expiry_date',
                    'qatar_id_number',
                    'qatar_id_expiry_date',
                    'occupation',
                    'passport_number',
                    'passport_expiry_date',
                    'document_serial_number',
                    'residency_type',
                    'employer',
                    'place_of_birth',
                    'passport_issue_date',
                    'issuing_country',
                    'issuing_authority',
                    'qatar_id_front_image_path',
                    'qatar_id_back_image_path',
                    'passport_image_path',
                    'profile_image_path',
                ];

                $sourceIdentity = [];
                $targetIdentity = [];

                foreach (
                    $identityFields
                    as $field
                ) {
                    $sourceIdentity[
                        $field
                    ] =
                        $source->{$field};

                    $targetIdentity[
                        $field
                    ] =
                        $target->{$field};
                }

                /*
                 * DEVICE_TRANSFER_AUDIT_TRAIL_V3B
                 *
                 * All fields other than customer identity,
                 * ownership label and updated_at are immutable
                 * during a same-zone ownership transfer.
                 */
                $allowedMutableFields =
                    array_merge(
                        $identityFields,
                        [
                            'parent_client_id',
                            'device_label',
                            'updated_at',
                        ]
                    );

                $protectedFields =
                    collect(
                        array_keys(
                            $device
                                ->getAttributes()
                        )
                    )
                        ->reject(
                            fn ($field) =>
                                in_array(
                                    $field,
                                    $allowedMutableFields,
                                    true
                                )
                        )
                        ->values();

                $protectedBefore = [];

                foreach (
                    $protectedFields
                    as $field
                ) {
                    $protectedBefore[
                        $field
                    ] =
                        $device
                            ->getRawOriginal(
                                $field
                            );
                }

                $deviceBefore = [
                    'id' =>
                        (int) $device->id,

                    'client_code' =>
                        $device->client_code,

                    'parent_client_id' =>
                        $device->parent_client_id
                            ? (int)
                                $device
                                    ->parent_client_id
                            : null,

                    'device_label' =>
                        $device->device_label,

                    'mac_address' =>
                        $device->mac_address,

                    'active_mac_address' =>
                        $device
                            ->active_mac_address,

                    'ip_address' =>
                        $device->ip_address,

                    'router_id' =>
                        $device->router_id
                            ? (int)
                                $device->router_id
                            : null,

                    'ip_range_id' =>
                        $device->ip_range_id
                            ? (int)
                                $device->ip_range_id
                            : null,

                    'package_id' =>
                        $device->package_id
                            ? (int)
                                $device->package_id
                            : null,

                    'expiry_date' =>
                        $device->expiry_date
                            ?->toDateString(),

                    'enabled' =>
                        (bool) $device->enabled,

                    'connected' =>
                        (bool) $device->connected,

                    'mikrotik_lease_id' =>
                        $device
                            ->mikrotik_lease_id,

                    'mikrotik_arp_id' =>
                        $device
                            ->mikrotik_arp_id,

                    'mikrotik_queue_id' =>
                        $device
                            ->mikrotik_queue_id,
                ];

                $sourceBefore = [
                    'id' =>
                        (int) $source->id,

                    'client_code' =>
                        $source->client_code,

                    'name' =>
                        $source->name,
                ];

                $targetBefore = [
                    'id' =>
                        (int) $target->id,

                    'client_code' =>
                        $target->client_code,

                    'name' =>
                        $target->name,
                ];

                $promotedClientId =
                    null;

                $promotedClientCode =
                    null;

                /*
                 * MAIN_DEVICE_AUTO_PROMOTE_V2
                 */
                if ($isMainDevice) {
                    $remaining =
                        $sourceDevices
                            ->filter(
                                fn ($row) =>
                                    (int)
                                    $row->id
                                    !==
                                    (int)
                                    $device->id
                            )
                            ->sortBy(
                                'id'
                            )
                            ->values();

                    $promoted =
                        $remaining
                            ->first();

                    if ($promoted) {
                        $promotedModel =
                            Client::withoutGlobalScopes()
                                ->whereNull(
                                    'deleted_at'
                                )
                                ->whereKey(
                                    $promoted->id
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        $promotedModel
                            ->forceFill(
                                $sourceIdentity
                                + [
                                    'parent_client_id' =>
                                        null,

                                    'device_label' =>
                                        null,
                                ]
                            )
                            ->save();

                        $promotedClientId =
                            (int)
                            $promotedModel->id;

                        $promotedClientCode =
                            $promotedModel
                                ->client_code;

                        foreach (
                            $remaining
                                ->slice(1)
                            as $sibling
                        ) {
                            $siblingModel =
                                Client::withoutGlobalScopes()
                                    ->whereNull(
                                        'deleted_at'
                                    )
                                    ->whereKey(
                                        $sibling->id
                                    )
                                    ->lockForUpdate()
                                    ->firstOrFail();

                            $siblingModel
                                ->forceFill(
                                    $sourceIdentity
                                    + [
                                        'parent_client_id' =>
                                            $promotedClientId,
                                    ]
                                )
                                ->save();
                        }
                    }
                }

                /*
                 * Move selected physical device.
                 */
                $deviceLabel =
                    trim(
                        (string) (
                            $device
                                ->device_label
                            ?? ''
                        )
                    );

                if (
                    $deviceLabel
                    === ''
                ) {
                    $deviceLabel =
                        'Transferred Device';
                }

                $device
                    ->forceFill(
                        $targetIdentity
                        + [
                            'parent_client_id' =>
                                $target->id,

                            'device_label' =>
                                $deviceLabel,
                        ]
                    )
                    ->save();

                $protectedAfter = [];

                foreach (
                    $protectedFields
                    as $field
                ) {
                    $protectedAfter[
                        $field
                    ] =
                        $device
                            ->getRawOriginal(
                                $field
                            );
                }

                if (
                    $protectedBefore
                    !== $protectedAfter
                ) {
                    throw new \LogicException(
                        'Same-zone device ownership transfer attempted to modify protected physical/network/service data.'
                    );
                }

                $sourceDisappeared =
                    $isMainDevice
                    && $promotedClientId
                        === null;

                $deviceAfter = [
                    'id' =>
                        (int) $device->id,

                    'client_code' =>
                        $device->client_code,

                    'parent_client_id' =>
                        (int) $target->id,

                    'device_label' =>
                        $device->device_label,

                    'mac_address' =>
                        $device->mac_address,

                    'active_mac_address' =>
                        $device
                            ->active_mac_address,

                    'ip_address' =>
                        $device->ip_address,

                    'router_id' =>
                        $device->router_id
                            ? (int)
                                $device->router_id
                            : null,

                    'ip_range_id' =>
                        $device->ip_range_id
                            ? (int)
                                $device->ip_range_id
                            : null,

                    'package_id' =>
                        $device->package_id
                            ? (int)
                                $device->package_id
                            : null,

                    'expiry_date' =>
                        $device->expiry_date
                            ?->toDateString(),

                    'enabled' =>
                        (bool) $device->enabled,

                    'connected' =>
                        (bool) $device->connected,

                    'mikrotik_lease_id' =>
                        $device
                            ->mikrotik_lease_id,

                    'mikrotik_arp_id' =>
                        $device
                            ->mikrotik_arp_id,

                    'mikrotik_queue_id' =>
                        $device
                            ->mikrotik_queue_id,
                ];

                ResellerAuditLog::create([
                    'reseller_id' =>
                        (int)
                        $source->reseller_id,

                    'user_id' =>
                        (int) $actor->id,

                    'action' =>
                        'client_device_ownership_transferred',

                    'subject_type' =>
                        Client::class,

                    'subject_id' =>
                        (int)
                        $device->id,

                    'metadata' => [
                        'zone_id' =>
                            (int)
                            $source->zone_id,

                        'device_before' =>
                            $deviceBefore,

                        'device_after' =>
                            $deviceAfter,

                        'source_customer' =>
                            $sourceBefore,

                        'destination_customer' =>
                            $targetBefore,

                        'was_main_device' =>
                            $isMainDevice,

                        'promoted_client_id' =>
                            $promotedClientId,

                        'promoted_client_code' =>
                            $promotedClientCode,

                        'source_disappeared' =>
                            $sourceDisappeared,

                        'network_changed' =>
                            false,

                        'quota_slot_delta' =>
                            0,
                    ],

                    'ip_address' =>
                        $ipAddress,
                ]);

                return [
                    'device_id' =>
                        (int)
                        $device->id,

                    'device_code' =>
                        $device
                            ->client_code,

                    'was_main_device' =>
                        $isMainDevice,

                    'source_client_id' =>
                        (int)
                        $source->id,

                    'source_name' =>
                        $source->name,

                    'target_client_id' =>
                        (int)
                        $target->id,

                    'target_name' =>
                        $target->name,

                    'promoted_client_id' =>
                        $promotedClientId,

                    'promoted_client_code' =>
                        $promotedClientCode,

                    'source_has_devices' =>
                        $promotedClientId
                        !== null,

                    'network_changed' =>
                        false,
                ];
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
