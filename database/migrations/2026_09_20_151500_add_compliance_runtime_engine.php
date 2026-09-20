<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_routers', function (Blueprint $table): void {
            $table->string('wan_interface')->nullable();
            $table->boolean('logging_ready')->default(false);
            $table->boolean('filtering_ready')->default(false);
            $table->timestamp('capability_checked_at')->nullable();
            $table->text('last_error')->nullable();
        });

        Schema::table('compliance_collectors', function (Blueprint $table): void {
            $table->foreignId('router_id')
                ->nullable()
                ->after('network_id')
                ->constrained('compliance_routers')
                ->nullOnDelete();
            $table->string('listen_ip', 45)->nullable();
            $table->unsignedInteger('ipfix_port')->default(2055);
            $table->string('token_prefix', 16)->nullable();
            $table->string('last_ip_address', 45)->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->index(['router_id', 'status']);
        });

        Schema::table('compliance_identity_bindings', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable();
            $table->index(['router_id', 'last_seen_at']);
        });

        Schema::table('compliance_browse_indexes', function (Blueprint $table): void {
            $table->string('event_type', 20)->default('flow');
            $table->unsignedInteger('source_port')->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->unsignedInteger('public_port')->nullable();
            $table->index(
                ['organization_id', 'public_ip', 'public_port', 'observed_at'],
                'cmp_browse_public_time_idx'
            );
        });

        Schema::create('compliance_collector_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('compliance_organizations')
                ->cascadeOnDelete();
            $table->foreignId('collector_id')
                ->constrained('compliance_collectors')
                ->cascadeOnDelete();
            $table->uuid('batch_uuid');
            $table->unsignedInteger('event_count')->default(0);
            $table->string('sha256', 64);
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(
                ['collector_id', 'batch_uuid'],
                'cmp_collector_batch_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_collector_batches');

        Schema::table('compliance_browse_indexes', function (Blueprint $table): void {
            $table->dropIndex('cmp_browse_public_time_idx');
            $table->dropColumn([
                'event_type',
                'source_port',
                'public_ip',
                'public_port',
            ]);
        });

        Schema::table('compliance_identity_bindings', function (Blueprint $table): void {
            $table->dropIndex(['router_id', 'last_seen_at']);
            $table->dropColumn('last_seen_at');
        });

        Schema::table('compliance_collectors', function (Blueprint $table): void {
            $table->dropIndex(['router_id', 'status']);
            $table->dropForeign(['router_id']);
            $table->dropColumn([
                'router_id',
                'listen_ip',
                'ipfix_port',
                'token_prefix',
                'last_ip_address',
                'registered_at',
                'revoked_at',
            ]);
        });

        Schema::table('compliance_routers', function (Blueprint $table): void {
            $table->dropColumn([
                'wan_interface',
                'logging_ready',
                'filtering_ready',
                'capability_checked_at',
                'last_error',
            ]);
        });
    }
};
