<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBillingPayment extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_billing_invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'notes',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            HotelBillingInvoice::class,
            'hotel_billing_invoice_id'
        );
    }
}
