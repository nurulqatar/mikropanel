<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryLog extends Model
{
    protected $fillable = [
        'event_key',
        'channel',
        'client_id',
        'recipient',
        'status',
        'provider',
        'attempts',
        'last_error',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];
}
