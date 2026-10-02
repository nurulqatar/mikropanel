<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;

class StorageTarget extends Model
{
    protected $table = 'compliance_storage_targets';

    protected $fillable = [
        'organization_id',
        'name',
        'driver',
        'config_encrypted',
        'health_status',
        'last_health_check_at',
        'enabled',
        'is_default',
        'last_error',
    ];

    protected $hidden = ['config_encrypted'];

    protected function casts(): array
    {
        return [
            'last_health_check_at' => 'datetime',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
