<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;

class IdentityBinding extends Model
{
    protected $table = 'compliance_identity_bindings';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
