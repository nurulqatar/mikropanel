<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRouterBinding extends Model
{
    protected $fillable = [
        'client_id',
        'router_id',

        'mikrotik_lease_id',
        'mikrotik_arp_id',
        'mikrotik_queue_id',

        'sync_status',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        )->withTrashed();
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(
            Router::class
        );
    }
}
