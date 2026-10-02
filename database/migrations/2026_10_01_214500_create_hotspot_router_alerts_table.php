<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotspot_router_alerts',
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
                        'router_id'
                    )
                    ->index();

                $table
                    ->string(
                        'alert_key',
                        120
                    );

                $table
                    ->enum(
                        'severity',
                        [
                            'warning',
                            'critical',
                        ]
                    )
                    ->default(
                        'warning'
                    )
                    ->index();

                $table
                    ->string(
                        'title',
                        255
                    );

                $table
                    ->text(
                        'message'
                    )
                    ->nullable();

                $table
                    ->boolean(
                        'repairable'
                    )
                    ->default(false);

                $table
                    ->boolean(
                        'active'
                    )
                    ->default(true)
                    ->index();

                $table
                    ->unsignedInteger(
                        'occurrences'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'first_seen_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'last_seen_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'resolved_at'
                    )
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'router_id',
                        'alert_key',
                    ],
                    'hotspot_router_alert_unique'
                );

                $table->index(
                    [
                        'reseller_id',
                        'active',
                        'severity',
                    ],
                    'hotspot_router_alert_reseller_active'
                );

                $table->index(
                    [
                        'zone_id',
                        'active',
                    ],
                    'hotspot_router_alert_zone_active'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotspot_router_alerts'
        );
    }
};
