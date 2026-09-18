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
