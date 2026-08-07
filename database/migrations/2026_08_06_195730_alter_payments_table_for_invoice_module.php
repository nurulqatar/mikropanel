<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->foreignId('invoice_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->renameColumn('method', 'payment_method');

            $table->renameColumn('note', 'notes');

            $table->unsignedBigInteger('received_by')
                ->nullable()
                ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->dropConstrainedForeignId('invoice_id');

            $table->renameColumn('payment_method', 'method');

            $table->renameColumn('notes', 'note');

            $table->dropColumn('received_by');
        });
    }
};
