<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {

            $table->foreignId('ip_range_id')
                ->nullable()
                ->after('router_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('client_code')->unique()->after('id');

            $table->string('mac_address')->unique()->after('name');

            $table->ipAddress('ip_address')
                ->nullable()
                ->after('mac_address');

            $table->date('installed_at')
                ->nullable()
                ->after('expiry_date');

            $table->unsignedTinyInteger('billing_day')
                ->default(1)
                ->after('installed_at');

            $table->string('address')
                ->nullable()
                ->after('email');

            $table->string('mikrotik_lease_id')
                ->nullable()
                ->after('connected');

            $table->string('mikrotik_arp_id')
                ->nullable()
                ->after('mikrotik_lease_id');

            $table->string('mikrotik_queue_id')
                ->nullable()
                ->after('mikrotik_arp_id');
        });

        Schema::table('clients', function (Blueprint $table) {

            $table->dropColumn([
                'username',
                'password',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {

            $table->string('username')->nullable();

            $table->string('password')->nullable();

            $table->dropForeign(['ip_range_id']);

            $table->dropColumn([
                'client_code',
                'ip_range_id',
                'mac_address',
                'ip_address',
                'installed_at',
                'billing_day',
                'address',
                'mikrotik_lease_id',
                'mikrotik_arp_id',
                'mikrotik_queue_id',
            ]);
        });
    }
};
