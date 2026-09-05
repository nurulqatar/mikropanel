<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotPlan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'validity_value',
        'validity_unit',
        'rate_limit',
        'shared_users',
        'idle_timeout_minutes',
        'keepalive_timeout_minutes',
        'mac_binding',
        'enabled',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'validity_value' => 'integer',
        'shared_users' => 'integer',
        'idle_timeout_minutes' => 'integer',
        'keepalive_timeout_minutes' => 'integer',
        'mac_binding' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(
            HotspotVoucher::class
        );
    }

    public function validitySeconds(): int
    {
        $value = max(
            1,
            (int) $this->validity_value
        );

        return match (
            $this->validity_unit
        ) {
            'minutes' =>
                $value * 60,

            'hours' =>
                $value * 3600,

            default =>
                $value * 86400,
        };
    }

    public function mikrotikProfileName(): string
    {
        return 'MP-HSP-'
            . $this->id;
    }
}
