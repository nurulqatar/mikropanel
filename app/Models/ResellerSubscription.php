<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerSubscription extends Model
{
    protected $fillable = [
        'reseller_id',
        'reseller_plan_id',
        'status',
        'client_limit',
        'operator_limit',
        'router_limit',
        'price',
        'starts_at',
        'expires_at',
        'grace_until',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'client_limit' => 'integer',
            'operator_limit' => 'integer',
            'router_limit' => 'integer',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_until' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(
            Reseller::class
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            ResellerPlan::class,
            'reseller_plan_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
