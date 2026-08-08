<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCustomFieldValue extends Model
{
    protected $fillable = [
        'client_id',
        'custom_field_id',
        'value',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(
            ClientCustomField::class,
            'custom_field_id'
        );
    }
}
