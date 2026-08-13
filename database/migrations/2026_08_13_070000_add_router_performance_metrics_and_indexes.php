<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->unsignedInteger('api_latency_ms')
                ->nullable()
                ->after('last_error');

            $table->unsignedInteger('sync_duration_ms')
                ->nullable()
                ->after('api_latency_ms');

            $table->index(
                ['enabled', 'last_checked_at'],
                'routers_enabled_checked_idx'
            );
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index(
                ['enabled', 'connected'],
                'clients_enabled_connected_idx'
            );

            $table->index(
                'expiry_date',
                'clients_expiry_date_idx'
            );
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(
                'payment_date',
                'payments_payment_date_idx'
            );
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(
                ['status', 'due_date'],
                'invoices_status_due_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(
                'invoices_status_due_date_idx'
            );
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(
                'payments_payment_date_idx'
            );
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(
                'clients_enabled_connected_idx'
            );

            $table->dropIndex(
                'clients_expiry_date_idx'
            );
        });

        Schema::table('routers', function (Blueprint $table) {
            $table->dropIndex(
                'routers_enabled_checked_idx'
            );

            $table->dropColumn([
                'api_latency_ms',
                'sync_duration_ms',
            ]);
        });
    }
};
