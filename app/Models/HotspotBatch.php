<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotBatch extends Model
{
    protected $fillable = [
        'batch_code',
        'hotspot_server_id',
        'hotspot_plan_id',
        'quantity',
        'prefix',
        'status',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
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

    public function vouchers(): HasMany
    {
        return $this->hasMany(
            HotspotVoucher::class
        );
    }
}
