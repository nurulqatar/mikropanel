<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerNotification extends Model
{
    protected $fillable = [
        'reseller_id',
        'type',
        'level',
        'title',
        'message',
        'dedupe_key',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(
            Reseller::class
        );
    }
}
