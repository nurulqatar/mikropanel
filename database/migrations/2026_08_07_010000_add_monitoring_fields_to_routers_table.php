<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = Schema::getColumnListing('routers');

        Schema::table('routers', function (Blueprint $table) use ($existing) {
            if (!in_array('identity', $existing, true)) {
                $table->string('identity')->nullable();
            }

            if (!in_array('routeros_version', $existing, true)) {
                $table->string('routeros_version')->nullable();
            }

            if (!in_array('board_name', $existing, true)) {
                $table->string('board_name')->nullable();
            }

            if (!in_array('uptime', $existing, true)) {
                $table->string('uptime')->nullable();
            }

            if (!in_array('cpu_load', $existing, true)) {
                $table->unsignedTinyInteger('cpu_load')->nullable();
            }

            if (!in_array('free_memory', $existing, true)) {
                $table->unsignedBigInteger('free_memory')->nullable();
            }

            if (!in_array('total_memory', $existing, true)) {
                $table->unsignedBigInteger('total_memory')->nullable();
            }

            if (!in_array('dhcp_leases_count', $existing, true)) {
                $table->unsignedInteger('dhcp_leases_count')->default(0);
            }

            if (!in_array('arp_entries_count', $existing, true)) {
                $table->unsignedInteger('arp_entries_count')->default(0);
            }

            if (!in_array('simple_queues_count', $existing, true)) {
                $table->unsignedInteger('simple_queues_count')->default(0);
            }

            if (!in_array('last_checked_at', $existing, true)) {
                $table->timestamp('last_checked_at')->nullable();
            }

            if (!in_array('last_seen_at', $existing, true)) {
                $table->timestamp('last_seen_at')->nullable();
            }

            if (!in_array('last_error', $existing, true)) {
                $table->text('last_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'identity',
            'routeros_version',
            'board_name',
            'uptime',
            'cpu_load',
            'free_memory',
            'total_memory',
            'dhcp_leases_count',
            'arp_entries_count',
            'simple_queues_count',
            'last_checked_at',
            'last_seen_at',
            'last_error',
        ];

        $existing = Schema::getColumnListing('routers');

        $dropColumns = array_values(
            array_intersect($columns, $existing)
        );

        if (!empty($dropColumns)) {
            Schema::table('routers', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }
    }
};
