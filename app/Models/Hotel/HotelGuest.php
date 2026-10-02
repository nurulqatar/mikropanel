<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelGuest extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'phone',
        'phone_country',
        'nationality',
        'identity_type',
        'identity_number',
        'identity_hash',
        'preferred_locale',
        'consent_at',
    ];

    protected $hidden = [
        'identity_number',
    ];

    protected function casts(): array
    {
        return [
            'identity_number' =>
                'encrypted',

            'consent_at' =>
                'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function stays(): HasMany
    {
        return $this->hasMany(
            HotelStay::class
        );
    }
}
