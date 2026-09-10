<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reseller_plans',
            function (Blueprint $table): void {
                $table->id();

                $table->string('name');
                $table->string('code')->unique();

                $table->unsignedInteger(
                    'client_limit'
                );

                $table->unsignedInteger(
                    'operator_limit'
                )->default(5);

                $table->unsignedInteger(
                    'router_limit'
                )->default(1);

                $table->decimal(
                    'price',
                    12,
                    2
                )->default(0);

                $table->unsignedInteger(
                    'validity_days'
                )->default(30);

                $table->boolean(
                    'active'
                )->default(true);

                $table->json(
                    'features'
                )->nullable();

                $table->text(
                    'notes'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'active',
                    'client_limit',
                ]);
            }
        );

        Schema::create(
            'resellers',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'code'
                )->unique();

                $table->string(
                    'company_name'
                );

                $table->string(
                    'owner_name'
                )->nullable();

                $table->string(
                    'email'
                )->nullable();

                $table->string(
                    'phone'
                )->nullable();

                $table->text(
                    'address'
                )->nullable();

                $table->string(
                    'status',
                    30
                )->default('pending');

                $table->decimal(
                    'wallet_balance',
                    14,
                    2
                )->default(0);

                $table->unsignedInteger(
                    'client_limit_override'
                )->nullable();

                $table->unsignedInteger(
                    'operator_limit_override'
                )->nullable();

                $table->unsignedInteger(
                    'router_limit_override'
                )->nullable();

                $table->string(
                    'expiry_mode',
                    40
                )->default('panel_lock');

                $table->string(
                    'timezone',
                    100
                )->default('Asia/Qatar');

                $table->string(
                    'currency',
                    10
                )->default('QAR');

                $table->timestamp(
                    'suspended_at'
                )->nullable();

                $table->text(
                    'suspension_reason'
                )->nullable();

                $table->foreignId(
                    'owner_user_id'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'created_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index([
                    'status',
                    'deleted_at',
                ]);
            }
        );

        Schema::create(
            'reseller_subscriptions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'reseller_id'
                )
                    ->constrained(
                        'resellers'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreignId(
                    'reseller_plan_id'
                )
                    ->nullable()
                    ->constrained(
                        'reseller_plans'
                    )
                    ->nullOnDelete();

                $table->string(
                    'status',
                    30
                )->default('active');

                /*
                 * Snapshot limits are stored here
                 * so editing a plan later does not
                 * silently change an old subscription.
                 */
                $table->unsignedInteger(
                    'client_limit'
                );

                $table->unsignedInteger(
                    'operator_limit'
                )->default(5);

                $table->unsignedInteger(
                    'router_limit'
                )->default(1);

                $table->decimal(
                    'price',
                    12,
                    2
                )->default(0);

                $table->timestamp(
                    'starts_at'
                );

                $table->timestamp(
                    'expires_at'
                );

                $table->timestamp(
                    'grace_until'
                )->nullable();

                $table->text(
                    'notes'
                )->nullable();

                $table->foreignId(
                    'created_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'reseller_id',
                    'status',
                    'expires_at',
                ]);
            }
        );

        Schema::create(
            'reseller_wallet_transactions',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'transaction_no'
                )->unique();

                $table->foreignId(
                    'reseller_id'
                )
                    ->constrained(
                        'resellers'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->string(
                    'type',
                    40
                );

                $table->string(
                    'direction',
                    10
                );

                $table->decimal(
                    'amount',
                    14,
                    2
                );

                $table->decimal(
                    'balance_before',
                    14,
                    2
                );

                $table->decimal(
                    'balance_after',
                    14,
                    2
                );

                $table->string(
                    'payment_method',
                    100
                )->nullable();

                $table->string(
                    'reference'
                )->nullable();

                $table->text(
                    'notes'
                )->nullable();

                $table->foreignId(
                    'created_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'reseller_id',
                    'created_at',
                ]);

                $table->index([
                    'type',
                    'direction',
                ]);
            }
        );

        Schema::create(
            'reseller_recharges',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'recharge_no'
                )->unique();

                $table->foreignId(
                    'reseller_id'
                )
                    ->constrained(
                        'resellers'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->decimal(
                    'amount',
                    14,
                    2
                );

                $table->string(
                    'payment_method',
                    100
                )->nullable();

                $table->string(
                    'reference'
                )->nullable();

                $table->string(
                    'status',
                    30
                )->default('pending');

                $table->foreignId(
                    'requested_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'approved_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'approved_at'
                )->nullable();

                $table->timestamp(
                    'rejected_at'
                )->nullable();

                $table->text(
                    'notes'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'reseller_id',
                    'status',
                    'created_at',
                ]);
            }
        );

        Schema::create(
            'reseller_usage_snapshots',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'reseller_id'
                )
                    ->constrained(
                        'resellers'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->date(
                    'snapshot_date'
                );

                $table->unsignedInteger(
                    'client_count'
                )->default(0);

                $table->unsignedInteger(
                    'operator_count'
                )->default(0);

                $table->unsignedInteger(
                    'router_count'
                )->default(0);

                $table->unsignedInteger(
                    'hotspot_voucher_count'
                )->default(0);

                $table->decimal(
                    'wallet_balance',
                    14,
                    2
                )->default(0);

                $table->timestamps();

                $table->unique([
                    'reseller_id',
                    'snapshot_date',
                ]);
            }
        );

        Schema::create(
            'reseller_audit_logs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'reseller_id'
                )
                    ->nullable()
                    ->constrained(
                        'resellers'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'user_id'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->string(
                    'action'
                );

                $table->string(
                    'subject_type'
                )->nullable();

                $table->unsignedBigInteger(
                    'subject_id'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->string(
                    'ip_address',
                    45
                )->nullable();

                $table->timestamps();

                $table->index([
                    'reseller_id',
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
            'reseller_audit_logs'
        );

        Schema::dropIfExists(
            'reseller_usage_snapshots'
        );

        Schema::dropIfExists(
            'reseller_recharges'
        );

        Schema::dropIfExists(
            'reseller_wallet_transactions'
        );

        Schema::dropIfExists(
            'reseller_subscriptions'
        );

        Schema::dropIfExists(
            'resellers'
        );

        Schema::dropIfExists(
            'reseller_plans'
        );
    }
};
