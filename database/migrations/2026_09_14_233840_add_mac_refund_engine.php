<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedInteger('service_validity_days')
                ->nullable();

            $table->decimal(
                'service_price_snapshot',
                12,
                2
            )->nullable();

            $table->date('service_start_date')
                ->nullable();

            $table->date('service_end_date')
                ->nullable();

            $table->decimal(
                'initial_due_amount',
                12,
                2
            )->default(0);

            $table->decimal(
                'refunded_amount',
                12,
                2
            )->default(0);

            $table->timestamp(
                'service_cancelled_at'
            )->nullable();
        });

        Schema::create(
            'client_refunds',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'reseller_id'
                )->nullable()->index();

                $table->uuid(
                    'batch_uuid'
                )->index();

                $table->foreignId(
                    'client_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId(
                    'invoice_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId(
                    'payment_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                $table->decimal(
                    'amount',
                    12,
                    2
                );

                $table->date(
                    'refund_date'
                )->index();

                $table->unsignedInteger(
                    'validity_days'
                );

                $table->unsignedInteger(
                    'used_days'
                );

                $table->decimal(
                    'daily_rate',
                    12,
                    6
                );

                $table->decimal(
                    'service_price',
                    12,
                    2
                );

                $table->decimal(
                    'used_value',
                    12,
                    2
                );

                $table->decimal(
                    'unused_value',
                    12,
                    2
                );

                $table->date(
                    'service_start_date'
                );

                $table->date(
                    'service_end_date'
                )->nullable();

                $table->date(
                    'client_expiry_before'
                )->nullable();

                $table->text(
                    'reason'
                );

                $table->unsignedBigInteger(
                    'refunded_by'
                )->nullable()->index();

                $table->timestamps();

                $table->index([
                    'invoice_id',
                    'payment_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'client_refunds'
        );

        Schema::table(
            'invoices',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'service_validity_days',
                    'service_price_snapshot',
                    'service_start_date',
                    'service_end_date',
                    'initial_due_amount',
                    'refunded_amount',
                    'service_cancelled_at',
                ]);
            }
        );
    }
};
