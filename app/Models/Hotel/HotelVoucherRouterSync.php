<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelVoucherRouterSync extends Model
{
    protected $fillable = [
        'hotel_voucher_id',
        'hotel_router_id',
        'status',
        'operation',
        'router_user_id',
        'attempts',
        'last_attempted_at',
        'synced_at',
        'expired_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attempts' =>
                'integer',

            'last_attempted_at' =>
                'datetime',

            'synced_at' =>
                'datetime',

            'expired_at' =>
                'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotelVoucher::class,
            'hotel_voucher_id'
        );
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            HotelRouter::class,
            'hotel_router_id'
        );
    }
}
