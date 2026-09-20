<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collector extends Model
{
    protected $table = 'compliance_collectors';

    protected $fillable = [
        'organization_id',
        'network_id',
        'router_id',
        'uuid',
        'name',
        'token_hash',
        'token_prefix',
        'version',
        'status',
        'capabilities',
        'listen_ip',
        'ipfix_port',
        'last_ip_address',
        'registered_at',
        'revoked_at',
        'last_seen_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'registered_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }
}
