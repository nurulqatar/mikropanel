<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPlan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'guest_limit',
        'is_guest_unlimited',
        'router_limit',
        'is_router_unlimited',
        'receptionist_limit',
        'is_receptionist_unlimited',
        'concurrent_limit',
        'price',
        'validity_days',
        'active',
        'features',
        'notes',
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

            'validity_days' =>
                'integer',

            'active' =>
                'boolean',

            'features' =>
                'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            HotelSubscription::class
        );
    }
}
