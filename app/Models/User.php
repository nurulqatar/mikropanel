<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'permissions',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
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
            $this->permissions ?? [],
            true
        );
    }

    public function hasAnyPermission(
        array $permissions
    ): bool {
        if ($this->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (
                $this->hasPermission(
                    $permission
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
