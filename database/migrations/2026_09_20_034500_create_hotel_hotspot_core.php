<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotel_plans',
            function (Blueprint $table): void {
                $table->id();

                $table->string('name');
                $table->string('code')->unique();

                $table
                    ->unsignedInteger('guest_limit')
                    ->nullable();

                $table
                    ->boolean('is_guest_unlimited')
                    ->default(false);

                $table
                    ->unsignedInteger('router_limit')
                    ->nullable();

                $table
                    ->boolean('is_router_unlimited')
                    ->default(false);

                $table
                    ->unsignedInteger('receptionist_limit')
                    ->nullable();

                $table
                    ->boolean('is_receptionist_unlimited')
                    ->default(false);

                $table
                    ->unsignedInteger('concurrent_limit')
                    ->nullable();

                $table
                    ->decimal('price', 12, 2)
                    ->default(0);

                $table
                    ->unsignedInteger('validity_days')
                    ->default(30);

                $table
                    ->boolean('active')
                    ->default(true);

                $table->json('features')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'hotels',
            function (Blueprint $table): void {
                $table->id();

                $table->string('code')->unique();
                $table->string('slug')->unique();

                $table->string('name');

                $table
                    ->string('status', 30)
                    ->default('active');

                $table->string('owner_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('whatsapp')->nullable();

                $table->text('address')->nullable();

                $table
                    ->string('timezone', 100)
                    ->default('Asia/Qatar');

                $table
                    ->string('currency', 10)
                    ->default('QAR');

                $table
                    ->time('check_out_time')
                    ->default('12:00:00');

                $table
                    ->string('portal_title')
                    ->default(
                        'Welcome to our Guest WiFi'
                    );

                $table
                    ->text('portal_subtitle')
                    ->nullable();

                $table
                    ->string('primary_color', 20)
                    ->default('#0f766e');

                $table
                    ->string('secondary_color', 20)
                    ->default('#0f172a');

                $table
                    ->string('default_locale', 20)
                    ->default('en');

                $table
                    ->json('enabled_locales')
                    ->nullable();

                $table
                    ->json('portal_translations')
                    ->nullable();

                $table
                    ->string('logo_path')
                    ->nullable();

                $table
                    ->string('background_path')
                    ->nullable();

                $table
                    ->longText('terms_text')
                    ->nullable();

                $table
                    ->longText('privacy_text')
                    ->nullable();

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('suspended_at')
                    ->nullable();

                $table
                    ->text('suspension_reason')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'status',
                    'name',
                ]);
            }
        );

        Schema::create(
            'hotel_subscriptions',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_plan_id')
                    ->nullable()
                    ->constrained('hotel_plans')
                    ->nullOnDelete();

                $table
                    ->string('status', 30)
                    ->default('active');

                $table
                    ->unsignedInteger('guest_limit')
                    ->nullable();

                $table
                    ->boolean('is_guest_unlimited')
                    ->default(false);

                $table
                    ->unsignedInteger('router_limit')
                    ->nullable();

                $table
                    ->boolean('is_router_unlimited')
                    ->default(false);

                $table
                    ->unsignedInteger('receptionist_limit')
                    ->nullable();

                $table
                    ->boolean('is_receptionist_unlimited')
                    ->default(false);

                $table
                    ->unsignedInteger('concurrent_limit')
                    ->nullable();

                $table
                    ->decimal('price', 12, 2)
                    ->default(0);

                $table->timestamp('starts_at');
                $table->timestamp('expires_at');

                $table->text('notes')->nullable();

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(
                    [
                        'hotel_id',
                        'status',
                        'expires_at',
                    ],
                    'hotel_subscription_idx'
                );
            }
        );

        Schema::create(
            'hotel_users',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table->string('name');

                /*
                 * Global unique email keeps Hotel login
                 * independent from Company accounts.
                 */
                $table->string('email')->unique();

                $table->string('password');

                $table
                    ->string('role', 30)
                    ->default('receptionist');

                $table
                    ->json('permissions')
                    ->nullable();

                $table
                    ->boolean('is_active')
                    ->default(true);

                $table
                    ->timestamp('last_login_at')
                    ->nullable();

                $table->rememberToken();
                $table->timestamps();

                $table->index([
                    'hotel_id',
                    'role',
                    'is_active',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotel_users'
        );

        Schema::dropIfExists(
            'hotel_subscriptions'
        );

        Schema::dropIfExists(
            'hotels'
        );

        Schema::dropIfExists(
            'hotel_plans'
        );
    }
};
