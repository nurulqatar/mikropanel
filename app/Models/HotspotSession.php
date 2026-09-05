<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotSession extends Model
{
    protected $fillable = [
        'hotspot_server_id',
        'hotspot_voucher_id',
        'mikrotik_active_id',
        'username',
        'mac_address',
        'address',
        'login_by',
        'uptime_seconds',
        'bytes_in',
        'bytes_out',
        'active',
        'started_at',
        'last_seen_at',
        'ended_at',
    ];

    protected $casts = [
        'uptime_seconds' => 'integer',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'active' => 'boolean',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(
            HotspotServer::class,
            'hotspot_server_id'
        );
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotspotVoucher::class,
            'hotspot_voucher_id'
        );
    }
}
