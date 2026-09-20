<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;

class NatMapping extends Model
{
    protected $table = 'compliance_nat_mappings';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
