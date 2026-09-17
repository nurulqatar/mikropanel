<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'zone_id',
        'invoice_id',
        'client_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function receiver()
    {
        return $this->belongsTo(
            User::class,
            'received_by'
        );
    }

    public function refunds()
    {
        return $this->hasMany(
            ClientRefund::class
        );
    }



    public function zone()
    {
        return $this->belongsTo(
            NetworkZone::class,
            'zone_id'
        );
    }


}
