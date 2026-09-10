<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable(
                'reseller_recharges'
            )
            || Schema::hasColumn(
                'reseller_recharges',
                'wallet_transaction_id'
            )
        ) {
            return;
        }

        Schema::table(
            'reseller_recharges',
            function (
                Blueprint $table
            ): void {
                $table->foreignId(
                    'wallet_transaction_id'
                )
                    ->nullable()
                    ->after('status')
                    ->constrained(
                        'reseller_wallet_transactions'
                    )
                    ->nullOnDelete();

                $table->index([
                    'status',
                    'approved_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        if (
            !Schema::hasTable(
                'reseller_recharges'
            )
            || !Schema::hasColumn(
                'reseller_recharges',
                'wallet_transaction_id'
            )
        ) {
            return;
        }

        Schema::table(
            'reseller_recharges',
            function (
                Blueprint $table
            ): void {
                $table->dropIndex([
                    'status',
                    'approved_at',
                ]);

                $table
                    ->dropConstrainedForeignId(
                        'wallet_transaction_id'
                    );
            }
        );
    }
};
