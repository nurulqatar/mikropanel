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
        'active_mac_address',
        'ip_address',

        'phone',
        'email',
        'address',
        'identity_type',
        'identity_number',
        'identity_barcode',
        'nationality',
        'date_of_birth',
        'gender',
        'document_expiry_date',
        'last_recharge_date',
        'imported_at',
        'import_batch_uuid',


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
        'date_of_birth' => 'date',
        'document_expiry_date' => 'date',
        'last_recharge_date' => 'date',
        'imported_at' => 'datetime',
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

    public function refunds(): HasMany
    {
        return $this->hasMany(
            ClientRefund::class
        );
    }

    public function monthlyUsages(): HasMany
    {
        return $this->hasMany(
            ClientMonthlyUsage::class
        );
    }

    public function routerBindings(): HasMany
    {
        return $this->hasMany(
            ClientRouterBinding::class
        );
    }


    public function customFieldValues()
    {
        return $this->hasMany(
            \App\Models\ClientCustomFieldValue::class,
            'client_id'
        );
    }

    public function customFields()
    {
        return $this->belongsToMany(
            \App\Models\ClientCustomField::class,
            'client_custom_field_values',
            'client_id',
            'custom_field_id'
        )
        ->withPivot('value')
        ->withTimestamps();
    }

}
