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
            !Schema::hasTable(
                'reseller_module_entitlements'
            )
        ) {
            Schema::create(
                'reseller_module_entitlements',
                function (
                    Blueprint $table
                ): void {
                    $table->id();

                    $table->foreignId(
                        'reseller_id'
                    )
                        ->constrained(
                            'resellers'
                        )
                        ->cascadeOnDelete();

                    $table->string(
                        'module_key',
                        64
                    );

                    $table->boolean(
                        'enabled'
                    )->default(false);

                    $table->timestamp(
                        'enabled_at'
                    )->nullable();

                    $table->timestamp(
                        'disabled_at'
                    )->nullable();

                    $table->foreignId(
                        'changed_by'
                    )
                        ->nullable()
                        ->constrained(
                            'users'
                        )
                        ->nullOnDelete();

                    $table->timestamps();

                    $table->unique([
                        'reseller_id',
                        'module_key',
                    ]);

                    $table->index([
                        'module_key',
                        'enabled',
                    ]);
                }
            );
        }

        /*
         * Existing production companies keep their
         * current behaviour after deployment.
         *
         * Super Admin can disable either module later.
         */
        $now = now();

        DB::table('resellers')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(
                200,
                function ($resellers) use (
                    $now
                ): void {
                    foreach (
                        $resellers as $reseller
                    ) {
                        foreach (
                            [
                                'mac_client',
                                'hotspot',
                            ]
                            as $module
                        ) {
                            DB::table(
                                'reseller_module_entitlements'
                            )->updateOrInsert(
                                [
                                    'reseller_id' =>
                                        $reseller->id,

                                    'module_key' =>
                                        $module,
                                ],
                                [
                                    'enabled' =>
                                        true,

                                    'enabled_at' =>
                                        $now,

                                    'disabled_at' =>
                                        null,

                                    'changed_by' =>
                                        null,

                                    'created_at' =>
                                        $now,

                                    'updated_at' =>
                                        $now,
                                ]
                            );
                        }
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reseller_module_entitlements'
        );
    }
};
