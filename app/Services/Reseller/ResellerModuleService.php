<?php

namespace App\Services\Reseller;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResellerModuleService
{
    public const MAC_CLIENT =
        'mac_client';

    public const HOTSPOT =
        'hotspot';

    public function definitions(): array
    {
        return [
            self::MAC_CLIENT => [
                'key' =>
                    self::MAC_CLIENT,

                'name' =>
                    'MAC Client',

                'description' =>
                    'MAC Client POS, clients, packages, IP pools, invoices, payments and MAC Manager Cash.',
            ],

            self::HOTSPOT => [
                'key' =>
                    self::HOTSPOT,

                'name' =>
                    'Hotspot',

                'description' =>
                    'Hotspot servers, plans, vouchers, seller collections, sessions, portal and Hotspot billing.',
            ],
        ];
    }

    public function keys(): array
    {
        return array_keys(
            $this->definitions()
        );
    }

    public function enabledKeysForResellerId(
        int $resellerId
    ): array {
        /*
         * Before migration completes, preserve the
         * old unrestricted production behaviour.
         */
        if (
            !Schema::hasTable(
                'reseller_module_entitlements'
            )
        ) {
            return $this->keys();
        }

        return DB::table(
            'reseller_module_entitlements'
        )
            ->where(
                'reseller_id',
                $resellerId
            )
            ->where(
                'enabled',
                true
            )
            ->whereIn(
                'module_key',
                $this->keys()
            )
            ->orderBy(
                'module_key'
            )
            ->pluck(
                'module_key'
            )
            ->all();
    }

    public function enabled(
        int $resellerId,
        string $module
    ): bool {
        return in_array(
            $module,
            $this->enabledKeysForResellerId(
                $resellerId
            ),
            true
        );
    }

    public function anyEnabled(
        int $resellerId
    ): bool {
        return $this
            ->enabledKeysForResellerId(
                $resellerId
            ) !== [];
    }

    public function setEnabledKeys(
        int $resellerId,
        array $enabledKeys,
        ?int $changedBy = null
    ): void {
        $known =
            $this->keys();

        $enabledKeys =
            array_values(
                array_unique(
                    array_intersect(
                        $known,
                        $enabledKeys
                    )
                )
            );

        DB::transaction(
            function () use (
                $resellerId,
                $known,
                $enabledKeys,
                $changedBy
            ): void {
                foreach (
                    $known as $module
                ) {
                    $enabled =
                        in_array(
                            $module,
                            $enabledKeys,
                            true
                        );

                    $existing =
                        DB::table(
                            'reseller_module_entitlements'
                        )
                            ->where(
                                'reseller_id',
                                $resellerId
                            )
                            ->where(
                                'module_key',
                                $module
                            )
                            ->first();

                    $data = [
                        'enabled' =>
                            $enabled,

                        'changed_by' =>
                            $changedBy,

                        'updated_at' =>
                            now(),
                    ];

                    if ($enabled) {
                        $data[
                            'disabled_at'
                        ] = null;

                        $data[
                            'enabled_at'
                        ] =
                            $existing
                            && $existing
                                ->enabled_at
                                ? $existing
                                    ->enabled_at
                                : now();
                    } else {
                        $data[
                            'enabled_at'
                        ] =
                            $existing
                                ? $existing
                                    ->enabled_at
                                : null;

                        $data[
                            'disabled_at'
                        ] =
                            now();
                    }

                    DB::table(
                        'reseller_module_entitlements'
                    )->updateOrInsert(
                        [
                            'reseller_id' =>
                                $resellerId,

                            'module_key' =>
                                $module,
                        ],
                        [
                            ...$data,

                            'created_at' =>
                                $existing
                                    ? $existing
                                        ->created_at
                                    : now(),
                        ]
                    );
                }
            }
        );
    }

    /**
     * Empty array = shared route.
     *
     * Multiple module keys = ANY enabled module
     * grants access to that shared service.
     */
    public function requiredModulesForRoute(
        ?string $routeName
    ): array {
        if (!$routeName) {
            return [];
        }

        if (
            str_starts_with(
                $routeName,
                'hotspot.'
            )
        ) {
            return [
                self::HOTSPOT,
            ];
        }

        if (
            $routeName
                === 'reseller.mac-pos'
            || str_starts_with(
                $routeName,
                'reseller.mac-clients.'
            )
            || str_starts_with(
                $routeName,
                'reseller.transfers.'
            )
            || str_starts_with(
                $routeName,
                'reseller.managers.'
            )
            || str_starts_with(
                $routeName,
                'reseller.cash.'
            )
            || str_starts_with(
                $routeName,
                'clients.'
            )
            || str_starts_with(
                $routeName,
                'client-custom-fields.'
            )
            || str_starts_with(
                $routeName,
                'packages.'
            )
            || str_starts_with(
                $routeName,
                'ip-ranges.'
            )
            || str_starts_with(
                $routeName,
                'invoices.'
            )
            || str_starts_with(
                $routeName,
                'payments.'
            )
        ) {
            return [
                self::MAC_CLIENT,
            ];
        }

        if (
            str_starts_with(
                $routeName,
                'routers.'
            )
            || str_starts_with(
                $routeName,
                'accounting.'
            )
            || str_starts_with(
                $routeName,
                'expenses.'
            )
            || str_starts_with(
                $routeName,
                'reseller.zones.'
            )
            || str_starts_with(
                $routeName,
                'reseller.mikrotik-vpn.'
            )
        ) {
            return [
                self::MAC_CLIENT,
                self::HOTSPOT,
            ];
        }

        return [];
    }

    public function routeAllowedForReseller(
        int $resellerId,
        ?string $routeName
    ): bool {
        $required =
            $this
                ->requiredModulesForRoute(
                    $routeName
                );

        if ($required === []) {
            return true;
        }

        $enabled =
            $this
                ->enabledKeysForResellerId(
                    $resellerId
                );

        return array_intersect(
            $required,
            $enabled
        ) !== [];
    }
}
