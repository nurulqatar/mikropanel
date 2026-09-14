<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotspotBranding extends Model
{
    protected $fillable = [
        'brand_name',
        'portal_title',
        'support_phone',
        'support_text',
        'primary_color',
        'terms_text',
        'show_price',
        'show_qr',
        'updated_by',
    ];

    protected $casts = [
        'show_price' => 'boolean',
        'show_qr' => 'boolean',
    ];

    public static function defaults(): array
    {
        return [
            'brand_name' =>
                'MikroPanel Hotspot',

            'portal_title' =>
                'Welcome to WiFi',

            'support_phone' =>
                null,

            'support_text' =>
                null,

            'primary_color' =>
                '#0891b2',

            'terms_text' =>
                'Internet access is subject to the service terms and voucher validity.',

            'show_price' =>
                true,

            'show_qr' =>
                true,
        ];
    }

    public static function current(): array
    {
        $row = static::query()
            ->first();

        if (!$row) {
            return static::defaults();
        }

        return array_merge(
            static::defaults(),
            $row->only([
                'brand_name',
                'portal_title',
                'support_phone',
                'support_text',
                'primary_color',
                'terms_text',
                'show_price',
                'show_qr',
            ])
        );
    }
}
