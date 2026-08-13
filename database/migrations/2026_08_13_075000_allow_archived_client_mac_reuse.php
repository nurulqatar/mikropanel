<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'clients',
                'active_mac_address'
            )
        ) {
            /*
             * Active client:
             *   active_mac_address = MAC
             *
             * Archived client:
             *   active_mac_address = NULL
             *
             * A UNIQUE index allows many NULL values,
             * while still preventing two active clients
             * from sharing the same MAC.
             */
            DB::statement(
                "
                ALTER TABLE `clients`
                ADD COLUMN `active_mac_address`
                    VARCHAR(17)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN `deleted_at` IS NULL
                            THEN UPPER(`mac_address`)
                            ELSE NULL
                        END
                    ) STORED
                "
            );
        }

        $indexes = collect(
            DB::select(
                'SHOW INDEX FROM `clients`'
            )
        );

        if (
            !$indexes->contains(
                fn ($row) =>
                    ($row->Key_name ?? null)
                    === 'clients_active_mac_address_unique'
            )
        ) {
            DB::statement(
                "
                CREATE UNIQUE INDEX
                    `clients_active_mac_address_unique`
                ON `clients`
                    (`active_mac_address`)
                "
            );
        }

        /*
         * Remove the old global MAC uniqueness only
         * after the new active-only unique index exists.
         */
        $indexes = collect(
            DB::select(
                'SHOW INDEX FROM `clients`'
            )
        );

        if (
            $indexes->contains(
                fn ($row) =>
                    ($row->Key_name ?? null)
                    === 'clients_mac_address_unique'
            )
        ) {
            DB::statement(
                "
                ALTER TABLE `clients`
                DROP INDEX `clients_mac_address_unique`
                "
            );
        }
    }

    public function down(): void
    {
        /*
         * Rolling back after archived MAC reuse may be
         * impossible because two historical rows may now
         * legitimately contain the same MAC.
         */
        $duplicates = DB::table('clients')
            ->selectRaw(
                'UPPER(mac_address) AS normalized_mac, COUNT(*) AS total'
            )
            ->whereNotNull('mac_address')
            ->groupByRaw(
                'UPPER(mac_address)'
            )
            ->havingRaw(
                'COUNT(*) > 1'
            )
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot rollback archived MAC reuse: duplicate historical MAC addresses now exist.'
            );
        }

        $indexes = collect(
            DB::select(
                'SHOW INDEX FROM `clients`'
            )
        );

        if (
            !$indexes->contains(
                fn ($row) =>
                    ($row->Key_name ?? null)
                    === 'clients_mac_address_unique'
            )
        ) {
            DB::statement(
                "
                CREATE UNIQUE INDEX
                    `clients_mac_address_unique`
                ON `clients`
                    (`mac_address`)
                "
            );
        }

        $indexes = collect(
            DB::select(
                'SHOW INDEX FROM `clients`'
            )
        );

        if (
            $indexes->contains(
                fn ($row) =>
                    ($row->Key_name ?? null)
                    === 'clients_active_mac_address_unique'
            )
        ) {
            DB::statement(
                "
                DROP INDEX
                    `clients_active_mac_address_unique`
                ON `clients`
                "
            );
        }

        if (
            Schema::hasColumn(
                'clients',
                'active_mac_address'
            )
        ) {
            DB::statement(
                "
                ALTER TABLE `clients`
                DROP COLUMN `active_mac_address`
                "
            );
        }
    }
};
