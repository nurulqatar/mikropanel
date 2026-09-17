<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkZone extends Model
{
    protected $fillable = [
        'reseller_id',
        'name',
        'code',
        'service_type',
        'address',
        'notes',
        'enabled',
    ];

    protected $casts = [
        'reseller_id' => 'integer',
        'enabled' => 'boolean',
    ];

    public function reseller()
    {
        return $this->belongsTo(
            Reseller::class
        );
    }

    public function routers(): HasMany
    {
        return $this->hasMany(
            Router::class,
            'zone_id'
        );
    }

    public function ipRanges(): HasMany
    {
        return $this->hasMany(
            IpRange::class,
            'zone_id'
        );
    }

    public function clients(): HasMany
    {
        return $this->hasMany(
            Client::class,
            'zone_id'
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(
            User::class,
            'zone_id'
        );
    }
}
