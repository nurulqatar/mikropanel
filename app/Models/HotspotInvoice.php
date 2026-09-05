<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotInvoice extends Model
{
    protected $fillable = [
        'hotspot_voucher_id',
        'invoice_no',
        'invoice_type',
        'amount',
        'discount',
        'paid_amount',
        'due_amount',
        'issue_date',
        'due_date',
        'service_from',
        'service_until',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'service_from' => 'datetime',
        'service_until' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(
            HotspotVoucher::class,
            'hotspot_voucher_id'
        )->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            HotspotPayment::class
        );
    }
}
