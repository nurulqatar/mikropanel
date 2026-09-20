<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;

class AppSignature extends Model
{
    protected $table = 'compliance_app_signatures';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'ip_ranges' => 'array',
            'ports' => 'array',
            'protocols' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
