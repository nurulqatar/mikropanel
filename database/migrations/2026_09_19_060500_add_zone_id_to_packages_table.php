<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'packages',
                'zone_id'
            )
        ) {
            Schema::table(
                'packages',
                function (Blueprint $table): void {
                    $table
                        ->foreignId('zone_id')
                        ->nullable()
                        ->after('reseller_id')
                        ->constrained('network_zones')
                        ->nullOnDelete();
                }
            );
        }

        /*
         * Backfill packages already used by clients.
         * Only unambiguous single-zone packages move.
         */
        $usage =
            DB::table('clients')
                ->select(
                    'package_id',
                    DB::raw(
                        'MIN(zone_id) as zone_id'
                    ),
                    DB::raw(
                        'COUNT(DISTINCT zone_id) as zone_count'
                    )
                )
                ->whereNotNull('package_id')
                ->whereNotNull('zone_id')
                ->groupBy('package_id')
                ->get();

        foreach ($usage as $row) {
            if (
                (int) $row->zone_count
                !== 1
            ) {
                continue;
            }

            DB::table('packages')
                ->where(
                    'id',
                    $row->package_id
                )
                ->whereNull('zone_id')
                ->update([
                    'zone_id' =>
                        $row->zone_id,
                ]);
        }

        /*
         * For an unused legacy package,
         * auto-assign only when its tenant has
         * exactly one enabled MAC zone.
         */
        $unassigned =
            DB::table('packages')
                ->whereNull('zone_id')
                ->get([
                    'id',
                    'reseller_id',
                ]);

        foreach ($unassigned as $package) {
            $query =
                DB::table('network_zones')
                    ->where(
                        'service_type',
                        'mac'
                    )
                    ->where(
                        'enabled',
                        true
                    );

            if (
                $package->reseller_id
                === null
            ) {
                $query->whereNull(
                    'reseller_id'
                );
            } else {
                $query->where(
                    'reseller_id',
                    $package->reseller_id
                );
            }

            $zones =
                $query
                    ->orderBy('id')
                    ->pluck('id');

            if ($zones->count() !== 1) {
                continue;
            }

            DB::table('packages')
                ->where(
                    'id',
                    $package->id
                )
                ->update([
                    'zone_id' =>
                        $zones->first(),
                ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'packages',
                'zone_id'
            )
        ) {
            Schema::table(
                'packages',
                function (Blueprint $table): void {
                    $table
                        ->dropConstrainedForeignId(
                            'zone_id'
                        );
                }
            );
        }
    }
};
