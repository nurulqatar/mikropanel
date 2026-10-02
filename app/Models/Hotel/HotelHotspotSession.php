<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelHotspotSession extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_router_id',
        'hotel_voucher_id',
        'session_key',
        'router_session_id',
        'username',
        'ip_address',
        'mac_address',
        'server_name',
        'login_by',
        'uptime',
        'session_time_left',
        'bytes_in',
        'bytes_out',
        'is_online',
        'started_at',
        'last_seen_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'is_online' => 'boolean',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            HotelRouter::class,
            'hotel_router_id'
        );
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotelVoucher::class,
            'hotel_voucher_id'
        );
    }
}
