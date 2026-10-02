<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'staff_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('staff_role', 20)
                    ->nullable()
                    ->after('zone_id');
            });
        }

        /*
         * Preserve all existing reseller staff.
         *
         * Existing operators must receive "operator",
         * otherwise the new OperatorController filter
         * would hide them.
         */
        DB::table('users')
            ->whereNotNull('reseller_id')
            ->where('role', 'operator')
            ->whereNull('staff_role')
            ->update([
                'staff_role' => 'operator',
            ]);

        /*
         * Preserve legacy manager accounts.
         */
        DB::table('users')
            ->whereNotNull('reseller_id')
            ->where('role', 'manager')
            ->whereNull('staff_role')
            ->update([
                'staff_role' => 'manager',
            ]);

        /*
         * New normal staff default to operator.
         * Existing unrelated users remain NULL.
         */
        DB::statement(
            "ALTER TABLE `users`
             MODIFY `staff_role`
             VARCHAR(20) NULL DEFAULT 'operator'"
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'staff_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('staff_role');
            });
        }
    }
};
