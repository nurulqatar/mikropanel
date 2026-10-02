<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'network_zones',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table
                    ->foreignId(
                        'reseller_id'
                    )
                    ->nullable()
                    ->constrained(
                        'resellers'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'name',
                    150
                );

                $table->string(
                    'code',
                    80
                );

                $table->enum(
                    'service_type',
                    [
                        'mac',
                        'hotspot',
                    ]
                );

                $table
                    ->string(
                        'address',
                        500
                    )
                    ->nullable();

                $table
                    ->text(
                        'notes'
                    )
                    ->nullable();

                $table
                    ->boolean(
                        'enabled'
                    )
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'reseller_id',
                    'service_type',
                    'enabled',
                ]);

                $table->unique(
                    [
                        'reseller_id',
                        'code',
                    ],
                    'network_zone_reseller_code_unique'
                );
            }
        );

        $zoneTables = [
            'routers',
            'ip_ranges',
            'clients',
            'users',
            'invoices',
            'payments',
            'client_refunds',
            'expenses',
            'client_monthly_usages',
            'hotspot_servers',
        ];

        foreach (
            $zoneTables
            as $tableName
        ) {
            if (
                !Schema::hasTable(
                    $tableName
                )
                || Schema::hasColumn(
                    $tableName,
                    'zone_id'
                )
            ) {
                continue;
            }

            Schema::table(
                $tableName,
                function (
                    Blueprint $table
                ): void {
                    $table
                        ->foreignId(
                            'zone_id'
                        )
                        ->nullable()
                        ->constrained(
                            'network_zones'
                        )
                        ->nullOnDelete();
                }
            );
        }

        /*
         * Platform MAC zone.
         */
        $platformMac =
            DB::table(
                'network_zones'
            )->insertGetId([
                'reseller_id' =>
                    null,

                'name' =>
                    'Platform Default MAC Zone',

                'code' =>
                    'PLATFORM-MAC',

                'service_type' =>
                    'mac',

                'address' =>
                    null,

                'notes' =>
                    'Automatic default zone for existing platform MAC records.',

                'enabled' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        /*
         * Platform Hotspot zone.
         */
        $platformHotspot =
            DB::table(
                'network_zones'
            )->insertGetId([
                'reseller_id' =>
                    null,

                'name' =>
                    'Platform Default Hotspot Zone',

                'code' =>
                    'PLATFORM-HOTSPOT',

                'service_type' =>
                    'hotspot',

                'address' =>
                    null,

                'notes' =>
                    'Automatic default zone for existing platform Hotspot records.',

                'enabled' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        foreach (
            [
                'routers',
                'ip_ranges',
                'clients',
            ]
            as $tableName
        ) {
            if (
                Schema::hasTable(
                    $tableName
                )
                && Schema::hasColumn(
                    $tableName,
                    'reseller_id'
                )
            ) {
                DB::table(
                    $tableName
                )
                    ->whereNull(
                        'reseller_id'
                    )
                    ->whereNull(
                        'zone_id'
                    )
                    ->update([
                        'zone_id' =>
                            $platformMac,
                    ]);
            }
        }

        if (
            Schema::hasTable(
                'hotspot_servers'
            )
            && Schema::hasColumn(
                'hotspot_servers',
                'reseller_id'
            )
        ) {
            DB::table(
                'hotspot_servers'
            )
                ->whereNull(
                    'reseller_id'
                )
                ->whereNull(
                    'zone_id'
                )
                ->update([
                    'zone_id' =>
                        $platformHotspot,
                ]);
        }

        if (
            Schema::hasTable(
                'expenses'
            )
            && Schema::hasColumn(
                'expenses',
                'reseller_id'
            )
        ) {
            DB::table(
                'expenses'
            )
                ->whereNull(
                    'reseller_id'
                )
                ->whereNull(
                    'zone_id'
                )
                ->update([
                    'zone_id' =>
                        $platformMac,
                ]);
        }

        /*
         * Each reseller gets two independent
         * service families.
         */
        $resellerIds =
            DB::table(
                'resellers'
            )
                ->orderBy('id')
                ->pluck('id');

        foreach (
            $resellerIds
            as $resellerId
        ) {
            $macZone =
                DB::table(
                    'network_zones'
                )->insertGetId([
                    'reseller_id' =>
                        $resellerId,

                    'name' =>
                        'Default MAC Zone',

                    'code' =>
                        'DEFAULT-MAC',

                    'service_type' =>
                        'mac',

                    'address' =>
                        null,

                    'notes' =>
                        'Existing MAC Client network.',

                    'enabled' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            $hotspotZone =
                DB::table(
                    'network_zones'
                )->insertGetId([
                    'reseller_id' =>
                        $resellerId,

                    'name' =>
                        'Default Hotspot Zone',

                    'code' =>
                        'DEFAULT-HOTSPOT',

                    'service_type' =>
                        'hotspot',

                    'address' =>
                        null,

                    'notes' =>
                        'Existing Hotspot network.',

                    'enabled' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            foreach (
                [
                    'routers',
                    'ip_ranges',
                    'clients',
                ]
                as $tableName
            ) {
                if (
                    Schema::hasTable(
                        $tableName
                    )
                    && Schema::hasColumn(
                        $tableName,
                        'reseller_id'
                    )
                ) {
                    DB::table(
                        $tableName
                    )
                        ->where(
                            'reseller_id',
                            $resellerId
                        )
                        ->whereNull(
                            'zone_id'
                        )
                        ->update([
                            'zone_id' =>
                                $macZone,
                        ]);
                }
            }

            /*
             * Existing operators remain attached
             * to their current/default MAC site.
             */
            DB::table(
                'users'
            )
                ->where(
                    'reseller_id',
                    $resellerId
                )
                ->where(
                    'role',
                    'operator'
                )
                ->whereNull(
                    'zone_id'
                )
                ->update([
                    'zone_id' =>
                        $macZone,
                ]);

            if (
                Schema::hasTable(
                    'hotspot_servers'
                )
                && Schema::hasColumn(
                    'hotspot_servers',
                    'reseller_id'
                )
            ) {
                DB::table(
                    'hotspot_servers'
                )
                    ->where(
                        'reseller_id',
                        $resellerId
                    )
                    ->whereNull(
                        'zone_id'
                    )
                    ->update([
                        'zone_id' =>
                            $hotspotZone,
                    ]);
            }

            if (
                Schema::hasTable(
                    'expenses'
                )
                && Schema::hasColumn(
                    'expenses',
                    'reseller_id'
                )
            ) {
                DB::table(
                    'expenses'
                )
                    ->where(
                        'reseller_id',
                        $resellerId
                    )
                    ->whereNull(
                        'zone_id'
                    )
                    ->update([
                        'zone_id' =>
                            $macZone,
                    ]);
            }
        }

        /*
         * Historical accounting follows client zone.
         */
        foreach (
            [
                'invoices',
                'payments',
                'client_refunds',
                'client_monthly_usages',
            ]
            as $tableName
        ) {
            if (
                !Schema::hasTable(
                    $tableName
                )
                || !Schema::hasColumn(
                    $tableName,
                    'client_id'
                )
            ) {
                continue;
            }

            DB::statement(
                '
                UPDATE '.$tableName.' f
                INNER JOIN clients c
                    ON c.id = f.client_id
                SET f.zone_id = c.zone_id
                WHERE f.zone_id IS NULL
                  AND c.zone_id IS NOT NULL
                '
            );
        }
    }

    public function down(): void
    {
        foreach (
            [
                'hotspot_servers',
                'client_monthly_usages',
                'expenses',
                'client_refunds',
                'payments',
                'invoices',
                'users',
                'clients',
                'ip_ranges',
                'routers',
            ]
            as $tableName
        ) {
            if (
                Schema::hasTable(
                    $tableName
                )
                && Schema::hasColumn(
                    $tableName,
                    'zone_id'
                )
            ) {
                Schema::table(
                    $tableName,
                    function (
                        Blueprint $table
                    ): void {
                        $table
                            ->dropConstrainedForeignId(
                                'zone_id'
                            );
                    }
                );
            }
        }

        Schema::dropIfExists(
            'network_zones'
        );
    }
};
