<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotSeller extends Model
{
    protected $fillable = [
        'reseller_id',
        'code',
        'name',
        'phone',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(
            HotspotVoucher::class
        );
    }

    public function batches(): HasMany
    {
        return $this->hasMany(
            HotspotBatch::class
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            HotspotInvoice::class
        );
    }

    public function collections(): HasMany
    {
        return $this->hasMany(
            HotspotSellerCollection::class
        );
    }
}
