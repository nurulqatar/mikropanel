<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilterRule extends Model
{
    protected $table = 'compliance_filter_rules';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'schedule' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id');
    }
}
