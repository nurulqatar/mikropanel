<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotel_wifi_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('code');

                $table
                    ->string('rate_limit')
                    ->nullable();

                $table
                    ->unsignedInteger('shared_users')
                    ->default(1);

                $table
                    ->unsignedBigInteger('data_limit_mb')
                    ->nullable();

                $table
                    ->boolean('is_default')
                    ->default(false);

                $table
                    ->boolean('enabled')
                    ->default(true);

                $table->timestamps();

                $table->unique(
                    [
                        'hotel_id',
                        'code',
                    ],
                    'hotel_wifi_profile_unique'
                );
            }
        );

        Schema::create(
            'hotel_guests',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table->string('name');

                $table
                    ->string('phone', 100)
                    ->nullable();

                $table
                    ->string('phone_country', 10)
                    ->nullable();

                $table
                    ->string('nationality', 120)
                    ->nullable();

                $table->string(
                    'identity_type',
                    30
                );

                /*
                 * Stored using Laravel encrypted cast.
                 */
                $table
                    ->longText('identity_number')
                    ->nullable();

                /*
                 * SHA-256 lookup value.
                 */
                $table
                    ->string('identity_hash', 64)
                    ->nullable();

                $table
                    ->string('preferred_locale', 20)
                    ->default('en');

                $table
                    ->timestamp('consent_at')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'hotel_id',
                    'phone',
                ]);

                $table->index(
                    [
                        'hotel_id',
                        'identity_hash',
                    ],
                    'hotel_guest_identity_idx'
                );
            }
        );

        Schema::create(
            'hotel_stays',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_guest_id')
                    ->constrained('hotel_guests')
                    ->cascadeOnDelete();

                $table
                    ->string('room_number', 100);

                $table->timestamp(
                    'check_in_at'
                );

                $table->timestamp(
                    'check_out_at'
                );

                $table
                    ->string('status', 30)
                    ->default('active');

                $table
                    ->string(
                        'registration_source',
                        30
                    )
                    ->default('portal');

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained(
                        'hotel_users'
                    )
                    ->nullOnDelete();

                $table
                    ->string(
                        'registration_ip',
                        45
                    )
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'hotel_id',
                        'status',
                        'check_out_at',
                    ],
                    'hotel_stay_expiry_idx'
                );

                $table->index([
                    'hotel_id',
                    'room_number',
                ]);
            }
        );

        Schema::create(
            'hotel_vouchers',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('hotel_id')
                    ->constrained('hotels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('hotel_stay_id')
                    ->constrained('hotel_stays')
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'hotel_wifi_profile_id'
                    )
                    ->nullable()
                    ->constrained(
                        'hotel_wifi_profiles'
                    )
                    ->nullOnDelete();

                $table
                    ->string('username')
                    ->unique();

                /*
                 * Router password will be stored encrypted.
                 */
                $table->longText(
                    'password'
                );

                $table
                    ->string(
                        'public_token',
                        80
                    )
                    ->unique();

                $table
                    ->string('status', 30)
                    ->default('unused');

                $table->timestamp(
                    'expires_at'
                );

                $table
                    ->timestamp('activated_at')
                    ->nullable();

                $table
                    ->timestamp('last_login_at')
                    ->nullable();

                $table
                    ->unsignedBigInteger('bytes_in')
                    ->default(0);

                $table
                    ->unsignedBigInteger('bytes_out')
                    ->default(0);

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained(
                        'hotel_users'
                    )
                    ->nullOnDelete();

                $table
                    ->string(
                        'creation_source',
                        30
                    )
                    ->default('portal');

                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    [
                        'hotel_id',
                        'status',
                        'expires_at',
                    ],
                    'hotel_voucher_expiry_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotel_vouchers'
        );

        Schema::dropIfExists(
            'hotel_stays'
        );

        Schema::dropIfExists(
            'hotel_guests'
        );

        Schema::dropIfExists(
            'hotel_wifi_profiles'
        );
    }
};
