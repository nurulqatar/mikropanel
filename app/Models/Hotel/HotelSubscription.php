<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelSubscription extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_plan_id',
        'status',
        'guest_limit',
        'is_guest_unlimited',
        'router_limit',
        'is_router_unlimited',
        'receptionist_limit',
        'is_receptionist_unlimited',
        'concurrent_limit',
        'price',
        'starts_at',
        'expires_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'guest_limit' =>
                'integer',

            'is_guest_unlimited' =>
                'boolean',

            'router_limit' =>
                'integer',

            'is_router_unlimited' =>
                'boolean',

            'receptionist_limit' =>
                'integer',

            'is_receptionist_unlimited' =>
                'boolean',

            'concurrent_limit' =>
                'integer',

            'price' =>
                'decimal:2',

            'starts_at' =>
                'datetime',

            'expires_at' =>
                'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            HotelPlan::class,
            'hotel_plan_id'
        );
    }
}
