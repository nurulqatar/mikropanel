<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotel_hotspot_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_router_id')
                    ->constrained('hotel_routers')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_voucher_id')
                    ->nullable()
                    ->constrained('hotel_vouchers')
                    ->nullOnDelete();

                $table
                    ->uuid('session_key')
                    ->unique();

                $table
                    ->string('router_session_id', 100)
                    ->nullable();

                $table
                    ->string('username', 191);

                $table
                    ->string('ip_address', 45)
                    ->nullable();

                $table
                    ->string('mac_address', 50)
                    ->nullable();

                $table
                    ->string('server_name', 191)
                    ->nullable();

                $table
                    ->string('login_by', 100)
                    ->nullable();

                $table
                    ->string('uptime', 100)
                    ->nullable();

                $table
                    ->string('session_time_left', 100)
                    ->nullable();

                $table
                    ->unsignedBigInteger('bytes_in')
                    ->default(0);

                $table
                    ->unsignedBigInteger('bytes_out')
                    ->default(0);

                $table
                    ->boolean('is_online')
                    ->default(true);

                $table
                    ->timestamp('started_at')
                    ->nullable();

                $table
                    ->timestamp('last_seen_at')
                    ->nullable();

                $table
                    ->timestamp('ended_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'hotel_id',
                        'is_online',
                        'last_seen_at',
                    ],
                    'hotel_hotspot_live_idx'
                );

                $table->index(
                    [
                        'hotel_router_id',
                        'is_online',
                    ],
                    'hotel_hotspot_router_idx'
                );

                $table->index(
                    [
                        'hotel_voucher_id',
                        'is_online',
                    ],
                    'hotel_hotspot_voucher_idx'
                );

                $table->index(
                    [
                        'hotel_id',
                        'created_at',
                    ],
                    'hotel_hotspot_history_idx'
                );

                $table->index(
                    [
                        'hotel_router_id',
                        'router_session_id',
                        'username',
                    ],
                    'hotel_hotspot_identity_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotel_hotspot_sessions'
        );
    }
};
