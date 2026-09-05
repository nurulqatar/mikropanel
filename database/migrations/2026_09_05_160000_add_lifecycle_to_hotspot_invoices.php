<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'hotspot_invoices',
            function (
                Blueprint $table
            ): void {
                $table->string(
                    'invoice_type',
                    30
                )
                    ->default('sale')
                    ->after('invoice_no');

                $table->timestamp(
                    'service_from'
                )
                    ->nullable()
                    ->after('due_date');

                $table->timestamp(
                    'service_until'
                )
                    ->nullable()
                    ->after('service_from');

                $table->index(
                    'invoice_type'
                );

                $table->index([
                    'service_from',
                    'service_until',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'hotspot_invoices',
            function (
                Blueprint $table
            ): void {
                $table->dropIndex([
                    'service_from',
                    'service_until',
                ]);

                $table->dropIndex([
                    'invoice_type',
                ]);

                $table->dropColumn([
                    'invoice_type',
                    'service_from',
                    'service_until',
                ]);
            }
        );
    }
};
