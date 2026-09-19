<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'zone_id',
        'name',
        'price',
        'validity_days',
        'speed_download',
        'speed_upload',
        'mikrotik_profile',
        'enabled',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'price' => 'decimal:2',
        'validity_days' => 'integer',
        'enabled' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            NetworkZone::class,
            'zone_id'
        );
    }

    public function clients(): HasMany
    {
        return $this->hasMany(
            Client::class
        );
    }
}
