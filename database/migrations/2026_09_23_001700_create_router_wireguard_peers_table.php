<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'router_wireguard_peers',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('router_id')
                    ->unique()
                    ->constrained('routers')
                    ->cascadeOnDelete();

                $table
                    ->unsignedBigInteger('reseller_id')
                    ->nullable()
                    ->index();

                $table
                    ->string(
                        'server_interface',
                        32
                    )
                    ->default('wg0');

                $table
                    ->string(
                        'server_public_key',
                        64
                    );

                $table
                    ->string(
                        'endpoint_host',
                        255
                    );

                $table
                    ->unsignedSmallInteger(
                        'endpoint_port'
                    )
                    ->default(51820);

                $table
                    ->text(
                        'client_private_key'
                    );

                $table
                    ->string(
                        'client_public_key',
                        64
                    )
                    ->unique();

                $table
                    ->string(
                        'client_ip',
                        45
                    )
                    ->unique();

                $table
                    ->string(
                        'previous_router_host',
                        255
                    )
                    ->nullable();

                $table
                    ->boolean('active')
                    ->default(false)
                    ->index();

                $table
                    ->timestamp(
                        'provisioned_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'revoked_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'last_handshake_at'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'rx_bytes'
                    )
                    ->default(0);

                $table
                    ->unsignedBigInteger(
                        'tx_bytes'
                    )
                    ->default(0);

                $table
                    ->string(
                        'last_endpoint',
                        255
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'last_status_check_at'
                    )
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'router_wireguard_peers'
        );
    }
};
