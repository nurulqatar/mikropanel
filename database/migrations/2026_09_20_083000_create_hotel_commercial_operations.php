<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotel_billing_invoices',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_subscription_id')
                    ->nullable()
                    ->constrained('hotel_subscriptions')
                    ->nullOnDelete();

                $table
                    ->string('invoice_no', 100)
                    ->unique();

                $table
                    ->decimal('amount', 12, 2)
                    ->default(0);

                $table
                    ->decimal('paid_amount', 12, 2)
                    ->default(0);

                $table
                    ->decimal('due_amount', 12, 2)
                    ->default(0);

                $table
                    ->string('currency', 10)
                    ->default('QAR');

                $table->date('issue_date');
                $table->date('due_date');

                $table
                    ->timestamp('period_start')
                    ->nullable();

                $table
                    ->timestamp('period_end')
                    ->nullable();

                $table
                    ->string('status', 30)
                    ->default('unpaid');

                $table
                    ->timestamp('paid_at')
                    ->nullable();

                $table
                    ->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    'hotel_subscription_id',
                    'hotel_invoice_subscription_unique'
                );

                $table->index([
                    'hotel_id',
                    'status',
                    'due_date',
                ]);
            }
        );

        Schema::create(
            'hotel_billing_payments',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_billing_invoice_id')
                    ->constrained('hotel_billing_invoices')
                    ->cascadeOnDelete();

                $table
                    ->decimal('amount', 12, 2);

                $table->date('payment_date');

                $table
                    ->string('payment_method', 100)
                    ->default('Cash');

                $table
                    ->string('reference', 191)
                    ->nullable();

                $table
                    ->text('notes')
                    ->nullable();

                $table
                    ->foreignId('received_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'hotel_id',
                    'payment_date',
                ]);
            }
        );

        Schema::create(
            'hotel_notifications',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->string('type', 100);

                $table
                    ->string('severity', 30)
                    ->default('info');

                $table
                    ->string('title', 191);

                $table->text('message');

                $table
                    ->string('dedupe_key', 191)
                    ->nullable();

                $table
                    ->string('action_url', 500)
                    ->nullable();

                $table
                    ->json('data')
                    ->nullable();

                $table
                    ->timestamp('read_at')
                    ->nullable();

                $table
                    ->timestamp('resolved_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'hotel_id',
                        'dedupe_key',
                    ],
                    'hotel_notification_dedupe_unique'
                );

                $table->index([
                    'hotel_id',
                    'read_at',
                    'created_at',
                ]);
            }
        );

        Schema::create(
            'hotel_audit_logs',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->nullable()
                    ->constrained('hotels')
                    ->nullOnDelete();

                $table
                    ->foreignId('hotel_user_id')
                    ->nullable()
                    ->constrained('hotel_users')
                    ->nullOnDelete();

                $table
                    ->string('actor_type', 50)
                    ->default('system');

                $table
                    ->string('actor_name', 191)
                    ->nullable();

                $table
                    ->string('action', 191);

                $table
                    ->string('subject_type', 191)
                    ->nullable();

                $table
                    ->unsignedBigInteger('subject_id')
                    ->nullable();

                $table
                    ->text('description')
                    ->nullable();

                $table
                    ->json('metadata')
                    ->nullable();

                $table
                    ->string('ip_address', 45)
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'hotel_id',
                    'created_at',
                ]);

                $table->index([
                    'subject_type',
                    'subject_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotel_audit_logs'
        );

        Schema::dropIfExists(
            'hotel_notifications'
        );

        Schema::dropIfExists(
            'hotel_billing_payments'
        );

        Schema::dropIfExists(
            'hotel_billing_invoices'
        );
    }
};
