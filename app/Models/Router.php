<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Router extends Model
{
    protected $fillable = [
        'name',
        'host',
        'api_port',
        'username',
        'password',
        'use_ssl',
        'enabled',
        'connected',
        'last_checked_at',
    ];

    protected $casts = [
        'use_ssl' => 'boolean',
        'enabled' => 'boolean',
        'connected' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function setPasswordAttribute($value): void
    {
        if (!empty($value)) {
            $this->attributes['password'] = encrypt($value);
        }
    }

    public function getPasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
