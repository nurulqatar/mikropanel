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
        'wallet_transaction_id',
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

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(
            ResellerWalletTransaction::class,
            'wallet_transaction_id'
        );
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
