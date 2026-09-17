<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'clients',
            function (Blueprint $table): void {
                $table
                    ->foreignId(
                        'parent_client_id'
                    )
                    ->nullable()
                    ->after('client_code')
                    ->constrained(
                        'clients'
                    )
                    ->nullOnDelete();

                $table
                    ->string(
                        'device_label',
                        100
                    )
                    ->nullable()
                    ->after(
                        'parent_client_id'
                    );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'clients',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'parent_client_id'
                );

                $table->dropColumn(
                    'device_label'
                );
            }
        );
    }
};
