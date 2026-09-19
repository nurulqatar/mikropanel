<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class ResellerSetting extends Model
{
    protected $fillable = [
        'reseller_id',
        'group',
        'key',
        'value',
        'type',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(
            Reseller::class
        );
    }

    public static function getValue(
        int $resellerId,
        string $key,
        mixed $default = null
    ): mixed {
        if (
            !Schema::hasTable(
                'reseller_settings'
            )
        ) {
            return $default;
        }

        $setting = static::query()
            ->where(
                'reseller_id',
                $resellerId
            )
            ->where(
                'key',
                $key
            )
            ->first();

        if (!$setting) {
            return $default;
        }

        return static::decode(
            $setting->value,
            $setting->type,
            $default
        );
    }

    public static function setValue(
        int $resellerId,
        string $key,
        mixed $value,
        string $group = 'company',
        string $type = 'string'
    ): self {
        return static::query()
            ->updateOrCreate(
                [
                    'reseller_id' =>
                        $resellerId,

                    'key' =>
                        $key,
                ],
                [
                    'group' =>
                        $group,

                    'value' =>
                        static::encode(
                            $value,
                            $type
                        ),

                    'type' =>
                        $type,
                ]
            );
    }

    public static function allDecodedFor(
        int $resellerId
    ): array {
        if (
            !Schema::hasTable(
                'reseller_settings'
            )
        ) {
            return [];
        }

        return static::query()
            ->where(
                'reseller_id',
                $resellerId
            )
            ->get()
            ->mapWithKeys(
                fn (ResellerSetting $setting): array => [
                    $setting->key =>
                        static::decode(
                            $setting->value,
                            $setting->type
                        ),
                ]
            )
            ->all();
    }

    private static function encode(
        mixed $value,
        string $type
    ): ?string {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' =>
                $value
                    ? '1'
                    : '0',

            'integer' =>
                (string) (int) $value,

            'float' =>
                (string) (float) $value,

            'json' =>
                json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            default =>
                (string) $value,
        };
    }

    private static function decode(
        ?string $value,
        string $type,
        mixed $default = null
    ): mixed {
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'boolean' =>
                in_array(
                    strtolower($value),
                    [
                        '1',
                        'true',
                        'yes',
                        'on',
                    ],
                    true
                ),

            'integer' =>
                (int) $value,

            'float' =>
                (float) $value,

            'json' =>
                json_decode(
                    $value,
                    true
                ) ?? $default,

            default =>
                $value,
        };
    }
}
