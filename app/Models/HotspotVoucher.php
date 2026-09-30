<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotspotVoucher extends Model
{

    /*
     * Main Hotspot RouterOS credential remains encrypted in DB
     * and usable internally, but is never exposed in JSON/Inertia.
     */
    protected $hidden = [
        'password',
    ];


    use SoftDeletes;

    protected $fillable = [
        'hotspot_batch_id',
        'hotspot_seller_id',
        'zone_id',
        'hotspot_server_id',
        'hotspot_plan_id',
        'username',
        'password',
        'status',
        'customer_name',
        'phone',
        'mac_address',
        'mikrotik_user_id',
        'sold_at',
        'activated_at',
        'expires_at',
        'last_login_at',
        'bytes_in',
        'bytes_out',
        'created_by',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'sold_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
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

    public function routerSyncs(): HasMany
    {
        return $this->hasMany(
            HotspotVoucherRouterSync::class,
            'hotspot_voucher_id'
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            HotspotBatch::class,
            'hotspot_batch_id'
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            HotspotInvoice::class
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            HotspotPayment::class
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            HotspotSession::class
        );
    }
}
