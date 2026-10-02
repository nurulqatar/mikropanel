<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reseller_registration_requests',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->uuid('public_token')
                    ->unique();

                $table
                    ->unsignedBigInteger(
                        'reseller_plan_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'reseller_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'owner_user_id'
                    )
                    ->nullable()
                    ->index();

                $table->string(
                    'company_name'
                );

                $table->string(
                    'owner_name'
                );

                $table
                    ->string(
                        'email'
                    )
                    ->index();

                $table
                    ->string(
                        'phone',
                        100
                    )
                    ->nullable()
                    ->index();

                $table
                    ->text('address')
                    ->nullable();

                $table->string(
                    'plan_name_snapshot'
                );

                $table->decimal(
                    'plan_price_snapshot',
                    12,
                    2
                );

                $table->unsignedInteger(
                    'plan_validity_days_snapshot'
                );

                $table->unsignedInteger(
                    'client_limit_snapshot'
                );

                $table
                    ->string(
                        'status',
                        20
                    )
                    ->default('pending')
                    ->index();

                $table
                    ->boolean(
                        'auto_approved'
                    )
                    ->default(false);

                $table
                    ->string(
                        'registration_ip',
                        64
                    )
                    ->nullable();

                $table
                    ->text('user_agent')
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'reviewed_by'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->timestamp(
                        'reviewed_at'
                    )
                    ->nullable();

                $table
                    ->text(
                        'review_notes'
                    )
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'status',
                        'created_at',
                    ],
                    'idx_reseller_registration_status'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reseller_registration_requests'
        );
    }
};
