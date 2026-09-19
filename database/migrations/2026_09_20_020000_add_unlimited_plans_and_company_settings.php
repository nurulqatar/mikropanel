<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('reseller_plans')
            && !Schema::hasColumn(
                'reseller_plans',
                'is_unlimited'
            )
        ) {
            Schema::table(
                'reseller_plans',
                function (Blueprint $table): void {
                    $table
                        ->boolean('is_unlimited')
                        ->default(false)
                        ->after('client_limit');
                }
            );
        }

        if (
            Schema::hasTable('reseller_subscriptions')
            && !Schema::hasColumn(
                'reseller_subscriptions',
                'is_unlimited'
            )
        ) {
            Schema::table(
                'reseller_subscriptions',
                function (Blueprint $table): void {
                    $table
                        ->boolean('is_unlimited')
                        ->default(false)
                        ->after('client_limit');
                }
            );
        }

        if (!Schema::hasTable('reseller_settings')) {
            Schema::create(
                'reseller_settings',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->foreignId('reseller_id')
                        ->constrained('resellers')
                        ->cascadeOnDelete();

                    $table
                        ->string('group')
                        ->default('company');

                    $table->string('key');

                    $table
                        ->longText('value')
                        ->nullable();

                    $table
                        ->string('type')
                        ->default('string');

                    $table->timestamps();

                    $table->unique(
                        [
                            'reseller_id',
                            'key',
                        ],
                        'reseller_setting_unique'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reseller_settings'
        );

        if (
            Schema::hasTable('reseller_subscriptions')
            && Schema::hasColumn(
                'reseller_subscriptions',
                'is_unlimited'
            )
        ) {
            Schema::table(
                'reseller_subscriptions',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'is_unlimited'
                    );
                }
            );
        }

        if (
            Schema::hasTable('reseller_plans')
            && Schema::hasColumn(
                'reseller_plans',
                'is_unlimited'
            )
        ) {
            Schema::table(
                'reseller_plans',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'is_unlimited'
                    );
                }
            );
        }
    }
};
