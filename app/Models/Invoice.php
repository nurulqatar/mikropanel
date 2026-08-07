<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
