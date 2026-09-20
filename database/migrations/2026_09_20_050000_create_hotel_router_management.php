<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotel_routers',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table->string(
                    'name',
                    150
                );

                $table->string(
                    'host',
                    45
                );

                $table
                    ->unsignedSmallInteger(
                        'api_port'
                    )
                    ->default(8728);

                $table->string(
                    'username',
                    150
                );

                $table->longText(
                    'password'
                );

                $table
                    ->boolean('use_ssl')
                    ->default(false);

                $table
                    ->boolean('enabled')
                    ->default(true);

                $table
                    ->string(
                        'status',
                        30
                    )
                    ->default('untested');

                $table
                    ->string(
                        'router_identity'
                    )
                    ->nullable();

                $table
                    ->string(
                        'routeros_version'
                    )
                    ->nullable();

                $table
                    ->string(
                        'architecture'
                    )
                    ->nullable();

                $table
                    ->string(
                        'guest_interface',
                        100
                    )
                    ->default('bridge');

                $table
                    ->string(
                        'guest_gateway_cidr',
                        50
                    )
                    ->default(
                        '10.55.0.1/24'
                    );

                $table
                    ->string(
                        'guest_pool_start',
                        45
                    )
                    ->default(
                        '10.55.0.10'
                    );

                $table
                    ->string(
                        'guest_pool_end',
                        45
                    )
                    ->default(
                        '10.55.0.254'
                    );

                $table
                    ->string(
                        'hotspot_server_name',
                        100
                    )
                    ->default(
                        'hotel-hotspot'
                    );

                $table
                    ->string(
                        'hotspot_profile_name',
                        100
                    )
                    ->default(
                        'hotel-guest-profile'
                    );

                $table
                    ->string(
                        'dns_name',
                        253
                    )
                    ->default(
                        'guest.wifi'
                    );

                $table
                    ->timestamp(
                        'last_tested_at'
                    )
                    ->nullable();

                $table
                    ->text(
                        'last_error'
                    )
                    ->nullable();

                $table
                    ->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'hotel_id',
                        'host',
                        'api_port',
                    ],
                    'hotel_router_endpoint_unique'
                );

                $table->index(
                    [
                        'hotel_id',
                        'enabled',
                        'status',
                    ],
                    'hotel_router_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotel_routers'
        );
    }
};
