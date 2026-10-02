<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * PACKAGE_ROAMING_COVERAGE_SCHEMA_V1
         *
         * Billing/Home zone remains packages.zone_id.
         *
         * coverage_mode:
         *   home_zone = package works only in its billing zone
         *   all_zones = package works in every MAC zone
         *               belonging to the same reseller
         */
        if (
            !Schema::hasColumn(
                'packages',
                'coverage_mode'
            )
        ) {
            Schema::table(
                'packages',
                function (
                    Blueprint $table
                ): void {
                    $table
                        ->string(
                            'coverage_mode',
                            20
                        )
                        ->default(
                            'home_zone'
                        )
                        ->after(
                            'zone_id'
                        );

                    $table->index(
                        'coverage_mode',
                        'packages_coverage_mode_idx'
                    );
                }
            );
        }

        /*
         * One physical client can have a different
         * local IP in each Network Zone.
         *
         * Multiple routers INSIDE the same zone may
         * reuse the same zone-local client IP.
         */
        $addZone =
            !Schema::hasColumn(
                'client_router_bindings',
                'zone_id'
            );

        $addRange =
            !Schema::hasColumn(
                'client_router_bindings',
                'ip_range_id'
            );

        $addIp =
            !Schema::hasColumn(
                'client_router_bindings',
                'ip_address'
            );

        if (
            $addZone
            || $addRange
            || $addIp
        ) {
            Schema::table(
                'client_router_bindings',
                function (
                    Blueprint $table
                ) use (
                    $addZone,
                    $addRange,
                    $addIp
                ): void {
                    if ($addZone) {
                        $table
                            ->unsignedBigInteger(
                                'zone_id'
                            )
                            ->nullable()
                            ->after(
                                'router_id'
                            );
                    }

                    if ($addRange) {
                        $table
                            ->unsignedBigInteger(
                                'ip_range_id'
                            )
                            ->nullable()
                            ->after(
                                'zone_id'
                            );
                    }

                    if ($addIp) {
                        $table
                            ->string(
                                'ip_address',
                                45
                            )
                            ->nullable()
                            ->after(
                                'ip_range_id'
                            );
                    }
                }
            );
        }

        /*
         * Index separately after columns exist.
         */
        $indexes =
            collect(
                DB::select(
                    "SHOW INDEX FROM client_router_bindings"
                )
            )
                ->pluck('Key_name')
                ->unique()
                ->values();

        if (
            !$indexes->contains(
                'crb_zone_ip_idx'
            )
        ) {
            Schema::table(
                'client_router_bindings',
                function (
                    Blueprint $table
                ): void {
                    $table->index(
                        [
                            'zone_id',
                            'ip_address',
                        ],
                        'crb_zone_ip_idx'
                    );
                }
            );
        }

        if (
            !$indexes->contains(
                'crb_ip_range_idx'
            )
        ) {
            Schema::table(
                'client_router_bindings',
                function (
                    Blueprint $table
                ): void {
                    $table->index(
                        'ip_range_id',
                        'crb_ip_range_idx'
                    );
                }
            );
        }

        /*
         * Existing bindings are home-zone bindings.
         * Backfill their existing network identity.
         *
         * This does NOT create, enable, disable or
         * update anything on MikroTik.
         */
        DB::statement(
            "
            UPDATE client_router_bindings AS b
            LEFT JOIN clients AS c
                ON c.id = b.client_id
            LEFT JOIN routers AS r
                ON r.id = b.router_id
            SET
                b.zone_id =
                    COALESCE(
                        r.zone_id,
                        c.zone_id
                    ),
                b.ip_range_id =
                    c.ip_range_id,
                b.ip_address =
                    c.ip_address
            WHERE
                b.zone_id IS NULL
                OR b.ip_range_id IS NULL
                OR b.ip_address IS NULL
            "
        );
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'client_router_bindings',
                'zone_id'
            )
        ) {
            Schema::table(
                'client_router_bindings',
                function (
                    Blueprint $table
                ): void {
                    try {
                        $table->dropIndex(
                            'crb_zone_ip_idx'
                        );
                    } catch (Throwable) {
                    }

                    try {
                        $table->dropIndex(
                            'crb_ip_range_idx'
                        );
                    } catch (Throwable) {
                    }

                    $table->dropColumn([
                        'zone_id',
                        'ip_range_id',
                        'ip_address',
                    ]);
                }
            );
        }

        if (
            Schema::hasColumn(
                'packages',
                'coverage_mode'
            )
        ) {
            Schema::table(
                'packages',
                function (
                    Blueprint $table
                ): void {
                    try {
                        $table->dropIndex(
                            'packages_coverage_mode_idx'
                        );
                    } catch (Throwable) {
                    }

                    $table->dropColumn(
                        'coverage_mode'
                    );
                }
            );
        }
    }
};
