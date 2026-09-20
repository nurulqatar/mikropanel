<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'compliance_rentals',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('organization_id')
                    ->constrained('compliance_organizations')
                    ->cascadeOnDelete();

                $table->foreignId('subscription_id')
                    ->nullable()
                    ->constrained('compliance_subscriptions')
                    ->nullOnDelete();

                $table->foreignId('plan_id')
                    ->constrained('compliance_plans')
                    ->restrictOnDelete();

                $table->string('rental_type', 30)
                    ->default('standalone');

                $table->string('source_type', 30)
                    ->nullable();

                $table->unsignedBigInteger('source_id')
                    ->nullable();

                $table->string('customer_name');

                $table->decimal('amount', 12, 2)
                    ->default(0);

                $table->string('currency', 10)
                    ->default('QAR');

                $table->string('payment_status', 30)
                    ->default('unpaid');

                $table->string('payment_method', 50)
                    ->nullable();

                $table->string('payment_reference', 255)
                    ->nullable();

                $table->string('status', 30)
                    ->default('active');

                $table->string('deployment_status', 50)
                    ->default('pending_hardware');

                $table->timestamp('starts_at');

                $table->timestamp('expires_at')
                    ->nullable();

                $table->timestamp('renewed_at')
                    ->nullable();

                $table->timestamp('suspended_at')
                    ->nullable();

                $table->text('notes')
                    ->nullable();

                $table->unsignedBigInteger('created_by')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['organization_id', 'status'],
                    'cmp_rental_org_status_idx'
                );

                $table->index(
                    ['source_type', 'source_id'],
                    'cmp_rental_source_idx'
                );

                $table->index(
                    ['expires_at', 'status'],
                    'cmp_rental_expiry_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_rentals');
    }
};
