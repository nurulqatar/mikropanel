<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotspotVoucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'hotspot_batch_id',
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
