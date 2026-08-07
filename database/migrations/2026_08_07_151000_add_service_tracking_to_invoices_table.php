<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'invoices',
                'applies_service_period'
            )
        ) {
            Schema::table(
                'invoices',
                function (Blueprint $table): void {
                    $table->boolean(
                        'applies_service_period'
                    )
                        ->default(true)
                        ->after('status');
                }
            );
        }

        if (
            !Schema::hasColumn(
                'invoices',
                'service_applied_at'
            )
        ) {
            Schema::table(
                'invoices',
                function (Blueprint $table): void {
                    $table->timestamp(
                        'service_applied_at'
                    )
                        ->nullable()
                        ->after(
                            'applies_service_period'
                        )
                        ->index();
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'invoices',
                'service_applied_at'
            )
        ) {
            Schema::table(
                'invoices',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'service_applied_at'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'invoices',
                'applies_service_period'
            )
        ) {
            Schema::table(
                'invoices',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'applies_service_period'
                    );
                }
            );
        }
    }
};
