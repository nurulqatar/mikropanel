<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerRegistrationRequest extends Model
{
    protected $fillable = [
        'public_token',
        'reseller_plan_id',
        'reseller_id',
        'owner_user_id',
        'company_name',
        'owner_name',
        'email',
        'phone',
        'address',
        'plan_name_snapshot',
        'plan_price_snapshot',
        'plan_validity_days_snapshot',
        'client_limit_snapshot',
        'status',
        'auto_approved',
        'registration_ip',
        'user_agent',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'reseller_plan_id' =>
                'integer',

            'reseller_id' =>
                'integer',

            'owner_user_id' =>
                'integer',

            'plan_price_snapshot' =>
                'decimal:2',

            'plan_validity_days_snapshot' =>
                'integer',

            'client_limit_snapshot' =>
                'integer',

            'auto_approved' =>
                'boolean',

            'reviewed_by' =>
                'integer',

            'reviewed_at' =>
                'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            ResellerPlan::class,
            'reseller_plan_id'
        );
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(
            Reseller::class
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_user_id'
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
