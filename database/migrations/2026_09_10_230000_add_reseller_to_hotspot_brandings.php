<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('hotspot_brandings')
            && !Schema::hasColumn(
                'hotspot_brandings',
                'reseller_id'
            )
        ) {
            Schema::table(
                'hotspot_brandings',
                function (Blueprint $table): void {
                    $table->foreignId(
                        'reseller_id'
                    )
                        ->nullable()
                        ->constrained(
                            'resellers'
                        )
                        ->restrictOnDelete();

                    $table->index(
                        'reseller_id'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('hotspot_brandings')
            && Schema::hasColumn(
                'hotspot_brandings',
                'reseller_id'
            )
        ) {
            Schema::table(
                'hotspot_brandings',
                function (Blueprint $table): void {
                    $table->dropIndex([
                        'reseller_id',
                    ]);

                    $table
                        ->dropConstrainedForeignId(
                            'reseller_id'
                        );
                }
            );
        }
    }
};
