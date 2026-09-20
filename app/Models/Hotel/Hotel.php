<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hotel extends Model
{
    protected $fillable = [
        'code',
        'slug',
        'name',
        'status',
        'owner_name',
        'email',
        'phone',
        'whatsapp',
        'address',
        'timezone',
        'currency',
        'check_out_time',
        'portal_title',
        'portal_subtitle',
        'primary_color',
        'secondary_color',
        'default_locale',
        'enabled_locales',
        'portal_translations',
        'logo_path',
        'background_path',
        'terms_text',
        'privacy_text',
        'created_by',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'enabled_locales' =>
                'array',

            'portal_translations' =>
                'array',

            'suspended_at' =>
                'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(
            HotelUser::class
        );
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            HotelSubscription::class
        );
    }

    public function activeSubscription(): HasOne
    {
        return $this
            ->hasOne(
                HotelSubscription::class
            )
            ->where(
                'status',
                'active'
            )
            ->latestOfMany();
    }
}
