<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;

class BrowseIndex extends Model
{
    protected $table = 'compliance_browse_indexes';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'blocked' => 'boolean',
        ];
    }
}
