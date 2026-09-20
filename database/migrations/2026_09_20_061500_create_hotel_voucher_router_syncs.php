<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotel_voucher_router_syncs',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'hotel_voucher_id'
                    )
                    ->constrained(
                        'hotel_vouchers'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'hotel_router_id'
                    )
                    ->constrained(
                        'hotel_routers'
                    )
                    ->cascadeOnDelete();

                $table
                    ->string(
                        'status',
                        30
                    )
                    ->default(
                        'pending'
                    );

                $table
                    ->string(
                        'operation',
                        20
                    )
                    ->default(
                        'upsert'
                    );

                $table
                    ->string(
                        'router_user_id',
                        100
                    )
                    ->nullable();

                $table
                    ->unsignedInteger(
                        'attempts'
                    )
                    ->default(0);

                $table
                    ->timestamp(
                        'last_attempted_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'synced_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'expired_at'
                    )
                    ->nullable();

                $table
                    ->text(
                        'last_error'
                    )
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'hotel_voucher_id',
                        'hotel_router_id',
                    ],
                    'hotel_voucher_router_sync_unique'
                );

                $table->index(
                    [
                        'status',
                        'last_attempted_at',
                    ],
                    'hotel_voucher_sync_status_idx'
                );

                $table->index(
                    [
                        'hotel_router_id',
                        'status',
                    ],
                    'hotel_router_sync_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotel_voucher_router_syncs'
        );
    }
};
