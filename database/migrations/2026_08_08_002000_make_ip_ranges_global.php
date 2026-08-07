<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ip_ranges',
            function (Blueprint $table) {
                /*
                 * These columns remain only for
                 * backward compatibility.
                 *
                 * New IP Pools are global.
                 */
                $table->unsignedBigInteger(
                    'router_id'
                )->nullable()->change();

                $table->string(
                    'interface'
                )->nullable()->change();
            }
        );
    }

    public function down(): void
    {
        /*
         * Deliberately no destructive automatic
         * rollback because global pools may have
         * NULL legacy router/interface values.
         */
    }
};
