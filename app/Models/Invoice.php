<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'client_id',
        'invoice_no',
        'billing_month',
        'amount',
        'discount',
        'paid_amount',
        'due_amount',
        'issue_date',
        'due_date',
        'status',
        'applies_service_period',
        'service_applied_at',
        'service_validity_days',
        'service_price_snapshot',
        'service_start_date',
        'service_end_date',
        'initial_due_amount',
        'refunded_amount',
        'service_cancelled_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'billing_month' => 'date',
        'issue_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'applies_service_period' => 'boolean',
        'service_applied_at' => 'datetime',
        'service_validity_days' => 'integer',
        'service_price_snapshot' => 'decimal:2',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
        'initial_due_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'service_cancelled_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function refunds()
    {
        return $this->hasMany(
            ClientRefund::class
        );
    }


    public function payments()
    {
        return $this->hasMany(
            Payment::class
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
