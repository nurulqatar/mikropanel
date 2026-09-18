<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ManagerCashLedgerEntry extends Model
{
    protected $fillable = [
        'reseller_id',
        'manager_id',
        'zone_id',
        'entry_date',
        'direction',
        'entry_type',
        'amount',
        'source_type',
        'source_id',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'reseller_id' => 'integer',
        'manager_id' => 'integer',
        'zone_id' => 'integer',
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'source_id' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(
            function (): void {
                throw new LogicException(
                    'Manager cash ledger entries are immutable.'
                );
            }
        );

        static::deleting(
            function (): void {
                throw new LogicException(
                    'Manager cash ledger entries cannot be deleted.'
                );
            }
        );
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'manager_id'
        );
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            NetworkZone::class,
            'zone_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
