<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table =
        'compliance_organizations';

    protected $fillable = [
        'name',
        'code',
        'account_type',
        'legal_profile',
        'country_code',
        'timezone',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'notes',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(
            User::class,
            'organization_id'
        );
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class,
            'organization_id'
        );
    }

    public function networks(): HasMany
    {
        return $this->hasMany(
            Network::class,
            'organization_id'
        );
    }

    public function routers(): HasMany
    {
        return $this->hasMany(
            Router::class,
            'organization_id'
        );
    }
}
