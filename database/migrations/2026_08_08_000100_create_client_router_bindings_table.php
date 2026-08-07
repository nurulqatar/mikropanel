<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable(
                'client_router_bindings'
            )
        ) {
            return;
        }

        Schema::create(
            'client_router_bindings',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('client_id')
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->foreignId('router_id')
                    ->constrained('routers')
                    ->cascadeOnDelete();

                /*
                 * IP is intentionally NOT stored here.
                 *
                 * clients.ip_address is the single
                 * global IP used on every MikroTik.
                 */
                $table->string(
                    'mikrotik_lease_id'
                )->nullable();

                $table->string(
                    'mikrotik_arp_id'
                )->nullable();

                $table->string(
                    'mikrotik_queue_id'
                )->nullable();

                $table->string(
                    'sync_status',
                    30
                )->default('pending');

                $table->timestamp(
                    'last_synced_at'
                )->nullable();

                $table->text(
                    'last_error'
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'client_id',
                    'router_id',
                ]);

                $table->index([
                    'router_id',
                    'sync_status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'client_router_bindings'
        );
    }
};
