<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTransferRequest extends Model
{
    protected $fillable = [
        'reseller_id',
        'client_id',
        'source_zone_id',
        'target_zone_id',
        'status',
        'network_status',
        'requested_by',
        'approved_by',
        'rejected_by',
        'cancelled_by',
        'source_router_id',
        'source_ip_range_id',
        'source_ip_address',
        'target_router_id',
        'target_ip_range_id',
        'target_ip_address',
        'source_service_end_date',
        'device_map',
        'request_note',
        'decision_note',
        'network_error',
        'requested_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'reseller_id' => 'integer',
        'client_id' => 'integer',
        'source_zone_id' => 'integer',
        'target_zone_id' => 'integer',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
        'rejected_by' => 'integer',
        'cancelled_by' => 'integer',
        'source_router_id' => 'integer',
        'source_ip_range_id' => 'integer',
        'target_router_id' => 'integer',
        'target_ip_range_id' => 'integer',
        'source_service_end_date' => 'date',
        'device_map' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /*
     * TRANSFER_NOTIFICATION_EVENTS_V1
     *
     * Transfer lifecycle events are written into
     * the existing reseller notification center.
     *
     * Notifications are transaction-aware because
     * model events run inside the same DB transaction.
     */
    protected static function booted(): void
    {
        static::created(
            function (
                ClientTransferRequest $transfer
            ): void {
                $transfer
                    ->writeTransferNotification(
                        'requested'
                    );
            }
        );

        static::updated(
            function (
                ClientTransferRequest $transfer
            ): void {
                if (
                    $transfer->wasChanged(
                        'status'
                    )
                    && in_array(
                        $transfer->status,
                        [
                            'approved',
                            'rejected',
                            'cancelled',
                        ],
                        true
                    )
                ) {
                    $transfer
                        ->writeTransferNotification(
                            $transfer->status
                        );
                }
            }
        );
    }

    private function writeTransferNotification(
        string $event
    ): void {
        if (
            !$this->id
            || !$this->reseller_id
        ) {
            return;
        }

        $client =
            Client::withoutGlobalScopes()
                ->find(
                    $this->client_id
                );

        $sourceZone =
            NetworkZone::withoutGlobalScopes()
                ->find(
                    $this->source_zone_id
                );

        $targetZone =
            NetworkZone::withoutGlobalScopes()
                ->find(
                    $this->target_zone_id
                );

        $clientLabel =
            $client
                ? trim(
                    (
                        $client->client_code
                        ? $client->client_code
                            . ' · '
                        : ''
                    )
                    . $client->name
                )
                : 'Client #'
                    . $this->client_id;

        $sourceName =
            $sourceZone?->name
            ?? 'Source Zone';

        $targetName =
            $targetZone?->name
            ?? 'Destination Zone';

        [
            $level,
            $title,
            $message,
        ] = match ($event) {
            'requested' => [
                'info',
                'Incoming Client Transfer',
                $clientLabel
                    . ' transfer requested from '
                    . $sourceName
                    . ' to '
                    . $targetName
                    . '.',
            ],

            'approved' => [
                'success',
                'Client Transfer Approved',
                $clientLabel
                    . ' was transferred from '
                    . $sourceName
                    . ' to '
                    . $targetName
                    . '.',
            ],

            'rejected' => [
                'warning',
                'Client Transfer Rejected',
                $clientLabel
                    . ' transfer to '
                    . $targetName
                    . ' was rejected.',
            ],

            'cancelled' => [
                'info',
                'Client Transfer Cancelled',
                $clientLabel
                    . ' transfer request was cancelled.',
            ],

            default => [
                'info',
                'Client Transfer Update',
                $clientLabel
                    . ' transfer status changed.',
            ],
        };

        ResellerNotification::withoutGlobalScopes()
            ->firstOrCreate(
                [
                    'dedupe_key' =>
                        'client-transfer:'
                        . $this->id
                        . ':'
                        . $event,
                ],
                [
                    'reseller_id' =>
                        $this->reseller_id,

                    'type' =>
                        'client_transfer',

                    'level' =>
                        $level,

                    'title' =>
                        $title,

                    'message' =>
                        $message,

                    'data' => [
                        'transfer_id' =>
                            $this->id,

                        'client_id' =>
                            $this->client_id,

                        'source_zone_id' =>
                            $this->source_zone_id,

                        'target_zone_id' =>
                            $this->target_zone_id,

                        'status' =>
                            $this->status,

                        'route' =>
                            'reseller.transfers.index',
                    ],
                ]
            );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function sourceZone(): BelongsTo
    {
        return $this->belongsTo(
            NetworkZone::class,
            'source_zone_id'
        );
    }

    public function targetZone(): BelongsTo
    {
        return $this->belongsTo(
            NetworkZone::class,
            'target_zone_id'
        );
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
