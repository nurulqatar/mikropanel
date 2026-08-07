<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'routers',
            function (Blueprint $table) {
                if (
                    !Schema::hasColumn(
                        'routers',
                        'client_interface'
                    )
                ) {
                    $table->string(
                        'client_interface',
                        100
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'routers',
                        'dhcp_server'
                    )
                ) {
                    $table->string(
                        'dhcp_server',
                        100
                    )->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'routers',
            function (Blueprint $table) {
                $drop = [];

                if (
                    Schema::hasColumn(
                        'routers',
                        'client_interface'
                    )
                ) {
                    $drop[] =
                        'client_interface';
                }

                if (
                    Schema::hasColumn(
                        'routers',
                        'dhcp_server'
                    )
                ) {
                    $drop[] =
                        'dhcp_server';
                }

                if ($drop !== []) {
                    $table->dropColumn(
                        $drop
                    );
                }
            }
        );
    }
};
