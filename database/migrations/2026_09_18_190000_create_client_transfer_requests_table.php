<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'client_transfer_requests',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table
                    ->unsignedBigInteger(
                        'reseller_id'
                    )
                    ->index();

                /*
                 * Primary/root client.
                 *
                 * Additional devices are transferred
                 * together and recorded in device_map.
                 */
                $table
                    ->unsignedBigInteger(
                        'client_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'source_zone_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'target_zone_id'
                    )
                    ->index();

                $table
                    ->string(
                        'status',
                        32
                    )
                    ->default(
                        'pending'
                    )
                    ->index();

                /*
                 * Network convergence result after
                 * destination approval.
                 */
                $table
                    ->string(
                        'network_status',
                        32
                    )
                    ->default(
                        'pending'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'requested_by'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'approved_by'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'rejected_by'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'cancelled_by'
                    )
                    ->nullable()
                    ->index();

                /*
                 * Source network snapshot.
                 */
                $table
                    ->unsignedBigInteger(
                        'source_router_id'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_ip_range_id'
                    )
                    ->nullable();

                $table
                    ->string(
                        'source_ip_address',
                        45
                    )
                    ->nullable();

                /*
                 * Destination network snapshot.
                 */
                $table
                    ->unsignedBigInteger(
                        'target_router_id'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'target_ip_range_id'
                    )
                    ->nullable();

                $table
                    ->string(
                        'target_ip_address',
                        45
                    )
                    ->nullable();

                /*
                 * Billing ownership audit.
                 *
                 * Existing invoices keep their source
                 * zone. New periods after transfer use
                 * the destination client zone.
                 */
                $table
                    ->date(
                        'source_service_end_date'
                    )
                    ->nullable();

                /*
                 * Old/new network information for the
                 * primary client and every extra device.
                 */
                $table
                    ->json(
                        'device_map'
                    )
                    ->nullable();

                $table
                    ->text(
                        'request_note'
                    )
                    ->nullable();

                $table
                    ->text(
                        'decision_note'
                    )
                    ->nullable();

                $table
                    ->text(
                        'network_error'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'requested_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'approved_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'rejected_at'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'cancelled_at'
                    )
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'reseller_id',
                    'status',
                ]);

                $table->index([
                    'source_zone_id',
                    'status',
                ]);

                $table->index([
                    'target_zone_id',
                    'status',
                ]);

                $table->index([
                    'client_id',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'client_transfer_requests'
        );
    }
};
