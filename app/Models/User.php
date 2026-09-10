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
        'reseller_id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'is_super_admin',
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
            'is_super_admin' => 'boolean',
            'reseller_id' => 'integer',
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

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin
            || (
                $this->reseller_id === null
                && $this->role === 'admin'
            );
    }

    public function isResellerUser(): bool
    {
        return $this->reseller_id !== null;
    }

    public function isResellerOwner(): bool
    {
        return $this->reseller_id !== null
            && $this->role === 'reseller';
    }

    public function reseller()
    {
        return $this->belongsTo(
            Reseller::class
        );
    }

}
