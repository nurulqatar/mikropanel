<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Network extends Model
{
    protected $table =
        'compliance_networks';

    protected $fillable = [
        'organization_id',
        'name',
        'site_code',
        'local_networks',
        'filter_mode',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'local_networks' =>
                'array',

            'enabled' =>
                'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'organization_id'
        );
    }

    public function routers(): HasMany
    {
        return $this->hasMany(
            Router::class,
            'network_id'
        );
    }
}
