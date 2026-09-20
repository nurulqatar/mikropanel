<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table =
        'compliance_subscriptions';

    protected $fillable = [
        'organization_id',
        'plan_id',
        'logging_enabled',
        'filtering_enabled',
        'billing_cycle',
        'custom_monthly_price',
        'status',
        'starts_at',
        'expires_at',
        'keep_last_filter_policy_on_expiry',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'logging_enabled' =>
                'boolean',

            'filtering_enabled' =>
                'boolean',

            'starts_at' =>
                'datetime',

            'expires_at' =>
                'datetime',

            'keep_last_filter_policy_on_expiry'
                => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            Plan::class,
            'plan_id'
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'organization_id'
        );
    }
}
