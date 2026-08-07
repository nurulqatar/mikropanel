<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMonthlyUsage extends Model
{
    protected $fillable = [
        'client_id',
        'usage_month',
        'upload_bytes',
        'download_bytes',
        'last_upload_counter',
        'last_download_counter',
        'last_synced_at',
    ];

    protected $casts = [
        'usage_month' => 'date',
        'upload_bytes' => 'integer',
        'download_bytes' => 'integer',
        'last_upload_counter' => 'integer',
        'last_download_counter' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
