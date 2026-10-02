<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotspot_device_resets',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->unsignedBigInteger(
                        'reseller_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'zone_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'hotspot_voucher_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'requested_router_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->string(
                        'old_mac_address',
                        17
                    )
                    ->nullable();

                $table
                    ->string(
                        'new_mac_address',
                        17
                    )
                    ->nullable();

                $table
                    ->enum(
                        'status',
                        [
                            'processing',
                            'success',
                            'partial',
                            'failed',
                        ]
                    )
                    ->default(
                        'processing'
                    )
                    ->index();

                $table
                    ->unsignedSmallInteger(
                        'routers_total'
                    )
                    ->default(0);

                $table
                    ->unsignedSmallInteger(
                        'routers_succeeded'
                    )
                    ->default(0);

                $table
                    ->unsignedSmallInteger(
                        'routers_failed'
                    )
                    ->default(0);

                $table
                    ->char(
                        'request_ip_hash',
                        64
                    )
                    ->nullable()
                    ->index();

                $table
                    ->text(
                        'failure_message'
                    )
                    ->nullable();

                $table
                    ->json(
                        'result_json'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'started_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'completed_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'rebound_at'
                    )
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'hotspot_voucher_id',
                        'status',
                        'created_at',
                    ],
                    'hotspot_device_reset_policy_idx'
                );

                $table->index(
                    [
                        'zone_id',
                        'created_at',
                    ],
                    'hotspot_device_reset_zone_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotspot_device_resets'
        );
    }
};
