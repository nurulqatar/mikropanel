<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('service_type', 30)->index();
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('source_subscription_id')->nullable();
            $table->unsignedBigInteger('source_plan_id')->nullable();
            $table->string('linked_service_type', 30)->nullable();
            $table->unsignedBigInteger('linked_source_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('currency', 10)->default('QAR');
            $table->string('plan_name')->nullable();
            $table->decimal('standard_price', 14, 2)->default(0);
            $table->decimal('agreed_price', 14, 2)->default(0);
            $table->unsignedInteger('billing_days')->default(30);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedInteger('grace_days')->default(3);
            $table->timestamp('grace_until')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('deployment_status', 40)->default('live')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->boolean('auto_invoice')->default(true);
            $table->boolean('auto_notify')->default(true);
            $table->char('portal_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['service_type', 'source_id']);
        });

        Schema::create('rental_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->string('dedupe_key')->nullable()->unique();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('unpaid')->index();
            $table->date('issue_date');
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')->cascadeOnDelete();
            $table->foreignId('rental_invoice_id')
                ->constrained('rental_invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method', 100)->default('Cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')->cascadeOnDelete();
            $table->string('event_type', 80)->index();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')->cascadeOnDelete();
            $table->string('category', 50)->default('general')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 30)->default('open')->index();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->string('level', 20)->default('info');
            $table->string('title');
            $table->text('message');
            $table->string('dedupe_key')->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_notifications');
        Schema::dropIfExists('rental_tickets');
        Schema::dropIfExists('rental_events');
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rental_invoices');
        Schema::dropIfExists('rental_contracts');
    }
};
