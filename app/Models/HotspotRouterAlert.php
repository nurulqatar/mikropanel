<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotRouterAlert extends Model
{
    protected $fillable = [
        'reseller_id',
        'zone_id',
        'router_id',
        'alert_key',
        'severity',
        'title',
        'message',
        'repairable',
        'active',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
    ];

    protected $casts = [
        'repairable' =>
            'boolean',

        'active' =>
            'boolean',

        'occurrences' =>
            'integer',

        'first_seen_at' =>
            'datetime',

        'last_seen_at' =>
            'datetime',

        'resolved_at' =>
            'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            Router::class
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
