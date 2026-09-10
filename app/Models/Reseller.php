<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reseller extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'company_name',
        'owner_name',
        'email',
        'phone',
        'address',
        'status',
        'wallet_balance',
        'client_limit_override',
        'operator_limit_override',
        'router_limit_override',
        'expiry_mode',
        'timezone',
        'currency',
        'suspended_at',
        'suspension_reason',
        'owner_user_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'client_limit_override' => 'integer',
            'operator_limit_override' => 'integer',
            'router_limit_override' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_user_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(
            User::class
        );
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            ResellerSubscription::class
        );
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(
            ResellerWalletTransaction::class
        );
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(
            ResellerRecharge::class
        );
    }

    public function clients(): HasMany
    {
        return $this->hasMany(
            Client::class
        );
    }

    public function routers(): HasMany
    {
        return $this->hasMany(
            Router::class
        );
    }

    public function activeSubscription()
    {
        return $this->hasOne(
            ResellerSubscription::class
        )
            ->where(
                'status',
                'active'
            )
            ->latestOfMany();
    }
}
