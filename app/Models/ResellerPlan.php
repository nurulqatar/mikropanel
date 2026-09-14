<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerPlan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'client_limit',
        'operator_limit',
        'router_limit',
        'price',
        'validity_days',
        'active',
        'features',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'client_limit' => 'integer',
            'operator_limit' => 'integer',
            'router_limit' => 'integer',
            'price' => 'decimal:2',
            'validity_days' => 'integer',
            'active' => 'boolean',
            'features' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            ResellerSubscription::class
        );
    }
}
