<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotspot_router_repair_audits',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->unsignedBigInteger('reseller_id')
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger('zone_id')
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger('router_id')
                    ->index();

                $table
                    ->unsignedBigInteger('requested_by')
                    ->nullable()
                    ->index();

                $table
                    ->string('status', 40)
                    ->default('processing')
                    ->index();

                $table
                    ->string('before_overall', 40)
                    ->nullable();

                $table
                    ->string('after_overall', 40)
                    ->nullable();

                $table
                    ->json('actions')
                    ->nullable();

                $table
                    ->json('skipped')
                    ->nullable();

                $table
                    ->json('rollback_actions')
                    ->nullable();

                $table
                    ->text('error_message')
                    ->nullable();

                $table
                    ->timestamp('started_at')
                    ->nullable();

                $table
                    ->timestamp('completed_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'router_id',
                        'created_at',
                    ],
                    'hotspot_repair_router_time_idx'
                );

                $table->index(
                    [
                        'reseller_id',
                        'zone_id',
                        'created_at',
                    ],
                    'hotspot_repair_tenant_time_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotspot_router_repair_audits'
        );
    }
};
