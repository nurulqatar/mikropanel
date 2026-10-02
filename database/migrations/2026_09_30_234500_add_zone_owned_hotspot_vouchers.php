<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hotspot_batches', 'zone_id')) {
            Schema::table('hotspot_batches', function (Blueprint $table): void {
                $table->foreignId('zone_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('network_zones')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('hotspot_batches', 'batch_name')) {
            Schema::table('hotspot_batches', function (Blueprint $table): void {
                $table->string('batch_name', 180)
                    ->nullable()
                    ->after('batch_code');
            });
        }

        if (!Schema::hasColumn('hotspot_vouchers', 'zone_id')) {
            Schema::table('hotspot_vouchers', function (Blueprint $table): void {
                $table->foreignId('zone_id')
                    ->nullable()
                    ->after('hotspot_seller_id')
                    ->constrained('network_zones')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('hotspot_voucher_router_syncs')) {
            Schema::create('hotspot_voucher_router_syncs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reseller_id')
                    ->nullable()
                    ->constrained('resellers')
                    ->nullOnDelete();
                $table->foreignId('zone_id')
                    ->nullable()
                    ->constrained('network_zones')
                    ->nullOnDelete();
                $table->foreignId('hotspot_voucher_id')
                    ->constrained('hotspot_vouchers')
                    ->cascadeOnDelete();
                $table->foreignId('router_id')
                    ->constrained('routers')
                    ->cascadeOnDelete();
                $table->foreignId('hotspot_server_id')
                    ->nullable()
                    ->constrained('hotspot_servers')
                    ->nullOnDelete();
                $table->string('mikrotik_user_id')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(
                    ['hotspot_voucher_id', 'router_id'],
                    'hsv_router_unique'
                );
            });
        }

        DB::statement(<<<'SQL'
UPDATE hotspot_batches hb
JOIN hotspot_servers hs
  ON hs.id = hb.hotspot_server_id
SET hb.zone_id = hs.zone_id
WHERE hb.zone_id IS NULL
  AND hs.zone_id IS NOT NULL
SQL);

        DB::statement(<<<'SQL'
UPDATE hotspot_vouchers hv
JOIN hotspot_servers hs
  ON hs.id = hv.hotspot_server_id
SET hv.zone_id = hs.zone_id
WHERE hv.zone_id IS NULL
  AND hs.zone_id IS NOT NULL
SQL);

        DB::table('hotspot_batches')
            ->whereNull('batch_name')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('hotspot_batches')
                        ->where('id', $row->id)
                        ->update([
                            'batch_name' => $row->batch_code,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_voucher_router_syncs');

        if (Schema::hasColumn('hotspot_vouchers', 'zone_id')) {
            Schema::table('hotspot_vouchers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('zone_id');
            });
        }

        if (Schema::hasColumn('hotspot_batches', 'zone_id')) {
            Schema::table('hotspot_batches', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('zone_id');
            });
        }

        if (Schema::hasColumn('hotspot_batches', 'batch_name')) {
            Schema::table('hotspot_batches', function (Blueprint $table): void {
                $table->dropColumn('batch_name');
            });
        }
    }
};
