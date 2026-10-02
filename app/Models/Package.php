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
        'coverage_mode',
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

    /*
     * PACKAGE_ROAMING_COVERAGE_MODEL_V1
     *
     * Package zone_id remains the financial /
     * billing home zone.
     */
    public function isAllZoneCoverage(): bool
    {
        return $this->coverage_mode
            === 'all_zones';
    }

}
