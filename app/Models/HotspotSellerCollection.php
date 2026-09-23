<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotSellerCollection extends Model
{
    protected $fillable = [
        'reseller_id',
        'hotspot_seller_id',
        'amount',
        'collected_at',
        'payment_method',
        'reference',
        'notes',
        'collected_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(
            HotspotSeller::class,
            'hotspot_seller_id'
        );
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'collected_by'
        );
    }
}
