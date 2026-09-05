<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotPayment extends Model
{
    protected $fillable = [
        'hotspot_invoice_id',
        'hotspot_voucher_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            HotspotInvoice::class,
            'hotspot_invoice_id'
        );
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotspotVoucher::class,
            'hotspot_voucher_id'
        );
    }
}
