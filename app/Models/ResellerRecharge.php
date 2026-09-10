<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerRecharge extends Model
{
    protected $fillable = [
        'recharge_no',
        'reseller_id',
        'amount',
        'payment_method',
        'reference',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(
            Reseller::class
        );
    }
}
