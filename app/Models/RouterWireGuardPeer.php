<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterWireGuardPeer extends Model
{
    /*
     * Laravel would infer:
     * router_wire_guard_peers
     *
     * Actual migration table:
     * router_wireguard_peers
     */
    protected $table = 'router_wireguard_peers';

    protected $fillable = [
        'router_id',
        'reseller_id',
        'label',
        'created_by_user_id',
        'server_interface',
        'server_public_key',
        'endpoint_host',
        'endpoint_port',
        'client_private_key',
        'client_public_key',
        'client_ip',
        'previous_router_host',
        'active',
        'provisioned_at',
        'revoked_at',
        'last_handshake_at',
        'rx_bytes',
        'tx_bytes',
        'last_endpoint',
        'last_status_check_at',
    ];

    protected $hidden = [
        'client_private_key',
    ];

    protected function casts(): array
    {
        return [
            'client_private_key' =>
                'encrypted',

            'endpoint_port' =>
                'integer',

            'active' =>
                'boolean',

            'provisioned_at' =>
                'datetime',

            'revoked_at' =>
                'datetime',

            'last_handshake_at' =>
                'datetime',

            'rx_bytes' =>
                'integer',

            'tx_bytes' =>
                'integer',

            'last_status_check_at' =>
                'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            Router::class
        );
    }
}
