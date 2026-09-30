<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotBatch extends Model
{
    protected $fillable = [
        'batch_code',
        'batch_name',
        'zone_id',
        'hotspot_server_id',
        'hotspot_plan_id',
        'hotspot_seller_id',
        'quantity',
        'prefix',
        'status',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /* HOTSPOT_SELLER_LEDGER_V1 */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(
            HotspotSeller::class,
            'hotspot_seller_id'
        );
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            NetworkZone::class,
            'zone_id'
        );
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(
            HotspotServer::class,
            'hotspot_server_id'
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            HotspotPlan::class,
            'hotspot_plan_id'
        );
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(
            HotspotVoucher::class
        );
    }
}
