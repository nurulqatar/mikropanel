<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('users')
            || !Schema::hasColumn(
                'users',
                'role'
            )
        ) {
            return;
        }

        $database = DB::getDatabaseName();

        $column = DB::table(
            'information_schema.COLUMNS'
        )
            ->where(
                'TABLE_SCHEMA',
                $database
            )
            ->where(
                'TABLE_NAME',
                'users'
            )
            ->where(
                'COLUMN_NAME',
                'role'
            )
            ->first();

        if (
            $column
            && strtolower(
                (string) $column->DATA_TYPE
            ) === 'enum'
        ) {
            DB::statement(
                "ALTER TABLE `users`
                 MODIFY `role`
                 VARCHAR(30)
                 NOT NULL
                 DEFAULT 'operator'"
            );
        }
    }

    public function down(): void
    {
        /*
         * Intentionally no automatic rollback
         * to an unknown historical ENUM definition.
         */
    }
};
