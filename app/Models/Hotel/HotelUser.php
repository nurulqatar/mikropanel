<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class HotelUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'hotel_id',
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' =>
                'hashed',

            'permissions' =>
                'array',

            'is_active' =>
                'boolean',

            'last_login_at' =>
                'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function isAdmin(): bool
    {
        return $this->role
            === 'admin';
    }

    public function isReceptionist(): bool
    {
        return $this->role
            === 'receptionist';
    }

    public function hasPermission(
        string $permission
    ): bool {
        if (!$this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return in_array(
            $permission,
            $this->permissions
                ?? [],
            true
        );
    }
}
