<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBillingInvoice extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_subscription_id',
        'invoice_no',
        'amount',
        'paid_amount',
        'due_amount',
        'currency',
        'issue_date',
        'due_date',
        'period_start',
        'period_end',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(
            Hotel::class
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            HotelBillingPayment::class,
            'hotel_billing_invoice_id'
        );
    }
}
