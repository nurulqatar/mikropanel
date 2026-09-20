<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Router extends Model
{
    protected $table =
        'compliance_routers';

    protected $fillable = [
        'organization_id',
        'network_id',
        'name',
        'vendor',
        'host',
        'management_port',
        'management_protocol',
        'api_username',
        'credential_encrypted',
        'wan_ip',
        'observed_public_ip',
        'cgnat_detected',
        'capabilities',
        'connection_status',
        'last_seen_at',
        'enabled',
        'notes',
    ];

    protected $hidden = [
        'credential_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'cgnat_detected' =>
                'boolean',

            'capabilities' =>
                'array',

            'last_seen_at' =>
                'datetime',

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

    public function network(): BelongsTo
    {
        return $this->belongsTo(
            Network::class,
            'network_id'
        );
    }
}
