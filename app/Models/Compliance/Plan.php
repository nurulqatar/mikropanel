<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table =
        'compliance_plans';

    protected $fillable = [
        'name',
        'code',
        'service_type',
        'monthly_price',
        'call_for_price',
        'site_limit',
        'router_limit',
        'collector_limit',
        'staff_limit',
        'retention_days',
        'log_volume_gb',
        'filter_rule_limit',
        'enabled',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' =>
                'decimal:2',

            'call_for_price' =>
                'boolean',

            'enabled' =>
                'boolean',
        ];
    }
}
