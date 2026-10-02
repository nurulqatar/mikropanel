<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelAuditLog extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_user_id',
        'actor_type',
        'actor_name',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function hotelUser(): BelongsTo
    {
        return $this->belongsTo(
            HotelUser::class
        );
    }
}
