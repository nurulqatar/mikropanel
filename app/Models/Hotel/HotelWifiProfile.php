<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelWifiProfile extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'code',
        'rate_limit',
        'shared_users',
        'data_limit_mb',
        'is_default',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'shared_users' =>
                'integer',

            'data_limit_mb' =>
                'integer',

            'is_default' =>
                'boolean',

            'enabled' =>
                'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }
}
