<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRouter extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'host',
        'api_port',
        'username',
        'password',
        'use_ssl',
        'enabled',
        'status',
        'router_identity',
        'routeros_version',
        'architecture',
        'guest_interface',
        'guest_gateway_cidr',
        'guest_pool_start',
        'guest_pool_end',
        'hotspot_server_name',
        'hotspot_profile_name',
        'dns_name',
        'last_tested_at',
        'last_error',
        'notes',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' =>
                'encrypted',

            'api_port' =>
                'integer',

            'use_ssl' =>
                'boolean',

            'enabled' =>
                'boolean',

            'last_tested_at' =>
                'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }
}
