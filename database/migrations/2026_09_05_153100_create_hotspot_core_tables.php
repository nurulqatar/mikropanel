<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotspot_servers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('router_id')
                    ->constrained('routers')
                    ->restrictOnDelete();

                $table->string('name');
                $table->string('mikrotik_name');

                $table->string('interface')
                    ->nullable();

                $table->string('address_pool')
                    ->nullable();

                $table->string('hotspot_profile')
                    ->nullable();

                $table->string('dns_name')
                    ->nullable();

                $table->boolean('enabled')
                    ->default(true);

                $table->boolean('connected')
                    ->default(false);

                $table->unsignedInteger('users_count')
                    ->default(0);

                $table->unsignedInteger('active_sessions_count')
                    ->default(0);

                $table->timestamp('last_synced_at')
                    ->nullable();

                $table->text('last_error')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'router_id',
                    'mikrotik_name',
                ]);

                $table->index([
                    'enabled',
                    'connected',
                ]);
            }
        );

        Schema::create(
            'hotspot_plans',
            function (Blueprint $table): void {
                $table->id();

                $table->string('name');

                $table->decimal(
                    'price',
                    12,
                    2
                );

                $table->unsignedInteger(
                    'validity_value'
                );

                $table->enum(
                    'validity_unit',
                    [
                        'minutes',
                        'hours',
                        'days',
                    ]
                )->default('days');

                $table->string('rate_limit')
                    ->nullable();

                $table->unsignedInteger(
                    'shared_users'
                )->default(1);

                $table->unsignedInteger(
                    'idle_timeout_minutes'
                )->nullable();

                $table->unsignedInteger(
                    'keepalive_timeout_minutes'
                )->nullable();

                $table->boolean('mac_binding')
                    ->default(false);

                $table->boolean('enabled')
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'enabled',
                    'name',
                ]);
            }
        );

        Schema::create(
            'hotspot_batches',
            function (Blueprint $table): void {
                $table->id();

                $table->string('batch_code')
                    ->unique();

                $table->foreignId(
                    'hotspot_server_id'
                )
                    ->constrained(
                        'hotspot_servers'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'hotspot_plan_id'
                )
                    ->constrained(
                        'hotspot_plans'
                    )
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'quantity'
                );

                $table->string('prefix')
                    ->nullable();

                $table->enum(
                    'status',
                    [
                        'ready',
                        'active',
                        'closed',
                    ]
                )->default('ready');

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
            }
        );

        Schema::create(
            'hotspot_vouchers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'hotspot_batch_id'
                )
                    ->nullable()
                    ->constrained(
                        'hotspot_batches'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'hotspot_server_id'
                )
                    ->constrained(
                        'hotspot_servers'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'hotspot_plan_id'
                )
                    ->constrained(
                        'hotspot_plans'
                    )
                    ->restrictOnDelete();

                $table->string('username')
                    ->unique();

                /*
                 * Stored encrypted by the model.
                 * It remains printable/decryptable by
                 * authorised panel code.
                 */
                $table->text('password');

                $table->enum(
                    'status',
                    [
                        'unused',
                        'active',
                        'expired',
                        'suspended',
                        'archived',
                    ]
                )->default('unused');

                $table->string('customer_name')
                    ->nullable();

                $table->string('phone')
                    ->nullable();

                $table->string('mac_address', 17)
                    ->nullable();

                $table->string('mikrotik_user_id')
                    ->nullable();

                $table->timestamp('sold_at')
                    ->nullable();

                $table->timestamp('activated_at')
                    ->nullable();

                $table->timestamp('expires_at')
                    ->nullable();

                $table->timestamp('last_login_at')
                    ->nullable();

                $table->unsignedBigInteger(
                    'bytes_in'
                )->default(0);

                $table->unsignedBigInteger(
                    'bytes_out'
                )->default(0);

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index([
                    'status',
                    'expires_at',
                ]);

                $table->index([
                    'hotspot_server_id',
                    'status',
                ]);

                $table->index('phone');
            }
        );

        Schema::create(
            'hotspot_invoices',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'hotspot_voucher_id'
                )
                    ->constrained(
                        'hotspot_vouchers'
                    )
                    ->restrictOnDelete();

                $table->string('invoice_no')
                    ->unique();

                $table->decimal(
                    'amount',
                    12,
                    2
                );

                $table->decimal(
                    'discount',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'paid_amount',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'due_amount',
                    12,
                    2
                )->default(0);

                $table->date('issue_date');
                $table->date('due_date');

                $table->enum(
                    'status',
                    [
                        'unpaid',
                        'partial',
                        'paid',
                        'cancelled',
                    ]
                )->default('unpaid');

                $table->text('notes')
                    ->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'status',
                    'due_date',
                ]);
            }
        );

        Schema::create(
            'hotspot_payments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'hotspot_invoice_id'
                )
                    ->constrained(
                        'hotspot_invoices'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'hotspot_voucher_id'
                )
                    ->constrained(
                        'hotspot_vouchers'
                    )
                    ->restrictOnDelete();

                $table->decimal(
                    'amount',
                    12,
                    2
                );

                $table->date(
                    'payment_date'
                );

                $table->string(
                    'payment_method'
                );

                $table->string(
                    'transaction_id'
                )->nullable();

                $table->text('notes')
                    ->nullable();

                $table->foreignId('received_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(
                    'payment_date'
                );
            }
        );

        Schema::create(
            'hotspot_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'hotspot_server_id'
                )
                    ->constrained(
                        'hotspot_servers'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'hotspot_voucher_id'
                )
                    ->nullable()
                    ->constrained(
                        'hotspot_vouchers'
                    )
                    ->nullOnDelete();

                $table->string(
                    'mikrotik_active_id'
                );

                $table->string('username');
                $table->string('mac_address')
                    ->nullable();

                $table->string('address')
                    ->nullable();

                $table->string('login_by')
                    ->nullable();

                $table->unsignedBigInteger(
                    'uptime_seconds'
                )->default(0);

                $table->unsignedBigInteger(
                    'bytes_in'
                )->default(0);

                $table->unsignedBigInteger(
                    'bytes_out'
                )->default(0);

                $table->boolean('active')
                    ->default(true);

                $table->timestamp('started_at')
                    ->nullable();

                $table->timestamp('last_seen_at')
                    ->nullable();

                $table->timestamp('ended_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'hotspot_server_id',
                    'mikrotik_active_id',
                ]);

                $table->index([
                    'active',
                    'last_seen_at',
                ]);

                $table->index('username');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotspot_sessions'
        );

        Schema::dropIfExists(
            'hotspot_payments'
        );

        Schema::dropIfExists(
            'hotspot_invoices'
        );

        Schema::dropIfExists(
            'hotspot_vouchers'
        );

        Schema::dropIfExists(
            'hotspot_batches'
        );

        Schema::dropIfExists(
            'hotspot_plans'
        );

        Schema::dropIfExists(
            'hotspot_servers'
        );
    }
};
