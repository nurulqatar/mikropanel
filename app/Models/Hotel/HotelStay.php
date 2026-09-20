<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelStay extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_guest_id',
        'room_number',
        'check_in_at',
        'check_out_at',
        'status',
        'registration_source',
        'created_by',
        'registration_ip',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' =>
                'datetime',

            'check_out_at' =>
                'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(
            HotelGuest::class,
            'hotel_guest_id'
        );
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(
            HotelVoucher::class
        );
    }
}
