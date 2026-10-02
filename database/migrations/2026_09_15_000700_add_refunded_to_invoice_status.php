<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE invoices
            MODIFY COLUMN status
            ENUM(
                'unpaid',
                'partial',
                'paid',
                'cancelled',
                'refunded'
            )
            NOT NULL
            DEFAULT 'unpaid'
        ");
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('status', 'refunded')
            ->update([
                'status' => 'cancelled',
            ]);

        DB::statement("
            ALTER TABLE invoices
            MODIFY COLUMN status
            ENUM(
                'unpaid',
                'partial',
                'paid',
                'cancelled'
            )
            NOT NULL
            DEFAULT 'unpaid'
        ");
    }
};
