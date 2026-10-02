<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotRouterRepairAudit extends Model
{
    protected $fillable = [
        'reseller_id',
        'zone_id',
        'router_id',
        'requested_by',
        'status',
        'before_overall',
        'after_overall',
        'actions',
        'skipped',
        'rollback_actions',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'actions' =>
            'array',

        'skipped' =>
            'array',

        'rollback_actions' =>
            'array',

        'started_at' =>
            'datetime',

        'completed_at' =>
            'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            Router::class
        );
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }
}
