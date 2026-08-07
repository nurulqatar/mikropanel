<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpRange extends Model
{
    protected $fillable = [
        'router_id',
        'name',
        'interface',
        'network',
        'gateway',
        'dns_server',
        'start_ip',
        'end_ip',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
