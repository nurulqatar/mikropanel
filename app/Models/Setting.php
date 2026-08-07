<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_encrypted',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public static function getValue(
        string $key,
        mixed $default = null
    ): mixed {
        try {
            $setting = static::query()
                ->where('key', $key)
                ->first();

            if (!$setting) {
                return $default;
            }

            return static::decodeSetting(
                $setting,
                $default
            );
        } catch (Throwable) {
            return $default;
        }
    }

    public static function setValue(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = 'string',
        bool $encrypted = false,
        ?string $description = null
    ): self {
        $storedValue = static::encodeValue(
            $value,
            $type,
            $encrypted
        );

        return static::query()->updateOrCreate(
            [
                'key' => $key,
            ],
            [
                'group' => $group,
                'value' => $storedValue,
                'type' => $type,
                'is_encrypted' => $encrypted,
                'description' => $description,
            ]
        );
    }

    public static function allDecoded(): array
    {
        try {
            return static::query()
                ->whereNotNull('key')
                ->get()
                ->mapWithKeys(function (
                    Setting $setting
                ): array {
                    return [
                        $setting->key =>
                            static::decodeSetting(
                                $setting
                            ),
                    ];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function bool(
        string $key,
        bool $default = false
    ): bool {
        return (bool) static::getValue(
            $key,
            $default
        );
    }

    private static function encodeValue(
        mixed $value,
        string $type,
        bool $encrypted
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'float' => (string) (float) $value,

            'json' => json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            default => (string) $value,
        };

        if ($encrypted && $value !== '') {
            return Crypt::encryptString($value);
        }

        return $value;
    }

    private static function decodeSetting(
        Setting $setting,
        mixed $default = null
    ): mixed {
        $value = $setting->value;

        if (
            $setting->is_encrypted
            && filled($value)
        ) {
            try {
                $value = Crypt::decryptString(
                    $value
                );
            } catch (Throwable) {
                return $default;
            }
        }

        return match ($setting->type) {
            'boolean' => in_array(
                $value,
                ['1', 1, true, 'true', 'yes', 'on'],
                true
            ),

            'integer' => (int) $value,
            'float' => (float) $value,

            'json' => json_decode(
                (string) $value,
                true
            ) ?? [],

            default => $value,
        };
    }
}
