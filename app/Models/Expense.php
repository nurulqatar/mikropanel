<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'zone_id',
        'expense_date',
        'category',
        'title',
        'amount',
        'payment_method',
        'notes',
        'created_by',
        'approval_status',
        'approved_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
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
