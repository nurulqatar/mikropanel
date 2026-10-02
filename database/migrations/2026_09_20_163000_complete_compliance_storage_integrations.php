<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_users', function (Blueprint $table): void {
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('panel_user_id')->nullable();

            $table->unique(
                ['organization_id', 'source_type', 'source_id'],
                'cmp_user_source_unique'
            );
            $table->index('panel_user_id', 'cmp_user_panel_idx');
        });

        Schema::table('compliance_storage_targets', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false);
            $table->text('last_error')->nullable();
            $table->index(
                ['organization_id', 'is_default'],
                'cmp_storage_default_idx'
            );
        });

        Schema::table('compliance_collector_batches', function (Blueprint $table): void {
            $table->string('archive_ref', 1500)->nullable();
            $table->string('archive_sha256', 64)->nullable();
        });

        Schema::create('compliance_storage_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('compliance_organizations')
                ->cascadeOnDelete();
            $table->foreignId('storage_target_id')
                ->constrained('compliance_storage_targets')
                ->cascadeOnDelete();
            $table->foreignId('collector_id')
                ->nullable()
                ->constrained('compliance_collectors')
                ->nullOnDelete();
            $table->string('object_key', 1000);
            $table->string('archive_ref', 1500);
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('event_count')->default(0);
            $table->timestamp('stored_at');
            $table->timestamps();
            $table->index(
                ['organization_id', 'stored_at'],
                'cmp_storage_object_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_storage_objects');

        Schema::table('compliance_collector_batches', function (Blueprint $table): void {
            $table->dropColumn(['archive_ref', 'archive_sha256']);
        });

        Schema::table('compliance_storage_targets', function (Blueprint $table): void {
            $table->dropIndex('cmp_storage_default_idx');
            $table->dropColumn(['is_default', 'last_error']);
        });

        Schema::table('compliance_users', function (Blueprint $table): void {
            $table->dropUnique('cmp_user_source_unique');
            $table->dropIndex('cmp_user_panel_idx');
            $table->dropColumn(['source_type', 'source_id', 'panel_user_id']);
        });
    }
};
