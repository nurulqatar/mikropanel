<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerUsageSnapshot extends Model
{
    protected $fillable = [
        'reseller_id',
        'snapshot_date',
        'client_count',
        'operator_count',
        'router_count',
        'hotspot_voucher_count',
        'wallet_balance',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'client_count' => 'integer',
            'operator_count' => 'integer',
            'router_count' => 'integer',
            'hotspot_voucher_count' => 'integer',
            'wallet_balance' => 'decimal:2',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(
            Reseller::class
        );
    }
}
