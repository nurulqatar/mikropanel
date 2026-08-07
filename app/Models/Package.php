<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name',
        'price',
        'validity_days',
        'speed_download',
        'speed_upload',
        'mikrotik_profile',
        'enabled',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'validity_days' => 'integer',
        'enabled' => 'boolean',
    ];
}
