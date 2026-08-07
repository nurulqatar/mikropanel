<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_code',
        'router_id',
        'ip_range_id',
        'package_id',

        'name',

        'mac_address',
        'ip_address',

        'phone',
        'email',
        'address',

        'expiry_date',
        'installed_at',
        'billing_day',

        'enabled',
        'connected',

        'mikrotik_lease_id',
        'mikrotik_arp_id',
        'mikrotik_queue_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'connected' => 'boolean',
        'expiry_date' => 'date',
        'installed_at' => 'date',
        'billing_day' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(
            Router::class
        );
    }

    public function package()
    {
        return $this->belongsTo(
            Package::class
        );
    }

    public function ipRange()
    {
        return $this->belongsTo(
            IpRange::class
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            Invoice::class
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class
        );
    }

    public function monthlyUsages(): HasMany
    {
        return $this->hasMany(
            ClientMonthlyUsage::class
        );
    }
}
