<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotDeviceReset extends Model
{
    protected $fillable = [
        'reseller_id',
        'zone_id',
        'hotspot_voucher_id',
        'requested_router_id',
        'old_mac_address',
        'new_mac_address',
        'status',
        'routers_total',
        'routers_succeeded',
        'routers_failed',
        'request_ip_hash',
        'failure_message',
        'result_json',
        'started_at',
        'completed_at',
        'rebound_at',
    ];

    protected $casts = [
        'routers_total' =>
            'integer',

        'routers_succeeded' =>
            'integer',

        'routers_failed' =>
            'integer',

        'result_json' =>
            'array',

        'started_at' =>
            'datetime',

        'completed_at' =>
            'datetime',

        'rebound_at' =>
            'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotspotVoucher::class,
            'hotspot_voucher_id'
        );
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            Router::class,
            'requested_router_id'
        );
    }
}
