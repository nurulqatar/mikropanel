<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCustomField extends Model
{
    protected $fillable = [
        'name',
        'field_key',
        'type',
        'placeholder',
        'options',
        'is_required',
        'is_enabled',
        'show_in_list',
        'show_in_reports',
        'show_in_invoice',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_enabled' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_reports' => 'boolean',
            'show_in_invoice' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(
            ClientCustomFieldValue::class,
            'custom_field_id'
        );
    }

    public function scopeEnabled($query)
    {
        return $query
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeForReports($query)
    {
        return $query
            ->where('is_enabled', true)
            ->where('show_in_reports', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeForList($query)
    {
        return $query
            ->where('is_enabled', true)
            ->where('show_in_list', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeForInvoice($query)
    {
        return $query
            ->where('is_enabled', true)
            ->where('show_in_invoice', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
