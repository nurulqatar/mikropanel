<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRefund extends Model
{
    protected $fillable = [
        'zone_id',
        'reseller_id',
        'batch_uuid',
        'client_id',
        'invoice_id',
        'payment_id',
        'amount',
        'refund_date',
        'validity_days',
        'used_days',
        'daily_rate',
        'service_price',
        'used_value',
        'unused_value',
        'service_start_date',
        'service_end_date',
        'client_expiry_before',
        'reason',
        'refunded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_date' => 'date',
        'validity_days' => 'integer',
        'used_days' => 'integer',
        'daily_rate' => 'decimal:6',
        'service_price' => 'decimal:2',
        'used_value' => 'decimal:2',
        'unused_value' => 'decimal:2',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
        'client_expiry_before' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(
            Client::class
        )->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class
        );
    }

    public function payment()
    {
        return $this->belongsTo(
            Payment::class
        );
    }

    public function refunder()
    {
        return $this->belongsTo(
            User::class,
            'refunded_by'
        );
    }

    public function zone()
    {
        return $this->belongsTo(
            NetworkZone::class,
            'zone_id'
        );
    }


}
