<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotVoucherRouterSync extends Model
{
    protected $fillable = [
        'reseller_id',
        'zone_id',
        'hotspot_voucher_id',
        'router_id',
        'hotspot_server_id',
        'mikrotik_user_id',
        'status',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotspotVoucher::class,
            'hotspot_voucher_id'
        )->withTrashed();
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(
            HotspotServer::class,
            'hotspot_server_id'
        );
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            NetworkZone::class,
            'zone_id'
        );
    }
}
