<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * VPN-FIRST_FLOW_V2
         *
         * A reseller can create the WireGuard peer before
         * any MikroTik Router record exists.
         */
        Schema::table(
            'router_wireguard_peers',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'router_id',
                ]);

                $table->dropUnique([
                    'router_id',
                ]);
            }
        );

        Schema::table(
            'router_wireguard_peers',
            function (Blueprint $table): void {
                $table
                    ->unsignedBigInteger('router_id')
                    ->nullable()
                    ->change();

                $table
                    ->string('label', 100)
                    ->nullable()
                    ->after('reseller_id');

                $table
                    ->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('label')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        );

        Schema::table(
            'router_wireguard_peers',
            function (Blueprint $table): void {
                $table
                    ->foreign('router_id')
                    ->references('id')
                    ->on('routers')
                    ->nullOnDelete();

                /*
                 * MySQL permits multiple NULL values in a
                 * unique index. One active/bound peer per
                 * Router remains enforced.
                 */
                $table->unique([
                    'router_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'router_wireguard_peers',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'router_id',
                ]);

                $table->dropUnique([
                    'router_id',
                ]);

                $table->dropForeign([
                    'created_by_user_id',
                ]);

                $table->dropColumn([
                    'label',
                    'created_by_user_id',
                ]);
            }
        );

        Schema::table(
            'router_wireguard_peers',
            function (Blueprint $table): void {
                $table
                    ->unsignedBigInteger('router_id')
                    ->nullable(false)
                    ->change();

                $table
                    ->foreign('router_id')
                    ->references('id')
                    ->on('routers')
                    ->cascadeOnDelete();

                $table->unique([
                    'router_id',
                ]);
            }
        );
    }
};
