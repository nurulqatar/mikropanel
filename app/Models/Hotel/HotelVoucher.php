<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelVoucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'hotel_stay_id',
        'hotel_wifi_profile_id',
        'username',
        'password',
        'public_token',
        'status',
        'expires_at',
        'activated_at',
        'last_login_at',
        'bytes_in',
        'bytes_out',
        'created_by',
        'creation_source',
    ];

    protected $hidden = [
        'password',
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'password' =>
                'encrypted',

            'expires_at' =>
                'datetime',

            'activated_at' =>
                'datetime',

            'last_login_at' =>
                'datetime',

            'bytes_in' =>
                'integer',

            'bytes_out' =>
                'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(
            HotelStay::class,
            'hotel_stay_id'
        );
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            HotelWifiProfile::class,
            'hotel_wifi_profile_id'
        );
    }
}
