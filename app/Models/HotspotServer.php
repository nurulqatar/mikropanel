<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotServer extends Model
{
    protected $fillable = [
        'router_id',
        'name',
        'mikrotik_name',
        'interface',
        'address_pool',
        'hotspot_profile',
        'dns_name',
        'enabled',
        'connected',
        'users_count',
        'active_sessions_count',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'connected' => 'boolean',
        'users_count' => 'integer',
        'active_sessions_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            Router::class
        );
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(
            HotspotVoucher::class
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            HotspotSession::class
        );
    }
}
