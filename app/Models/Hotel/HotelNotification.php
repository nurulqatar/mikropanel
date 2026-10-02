<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelNotification extends Model
{
    protected $fillable = [
        'hotel_id',
        'type',
        'severity',
        'title',
        'message',
        'dedupe_key',
        'action_url',
        'data',
        'read_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }
}
