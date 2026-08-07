<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'client_interface',
        'dhcp_server',
        'connected',
        'last_checked_at',
    ];

    protected $hidden = [
        'password',
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

    public function clientBindings(): HasMany
    {
        return $this->hasMany(
            ClientRouterBinding::class
        );
    }

}
