<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerCashHandover extends Model
{
    protected $fillable = [
        'reseller_id',
        'manager_id',
        'amount',
        'handover_date',
        'status',
        'notes',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'reseller_id' => 'integer',
        'manager_id' => 'integer',
        'amount' => 'decimal:2',
        'handover_date' => 'date',
        'submitted_by' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'manager_id'
        );
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }
}
