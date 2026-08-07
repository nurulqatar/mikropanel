<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {

            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('invoice_no')->unique();

            $table->date('billing_month');

            $table->decimal('amount', 12, 2);

            $table->decimal('discount', 12, 2)->default(0);

            $table->decimal('paid_amount', 12, 2)->default(0);

            $table->decimal('due_amount', 12, 2)->default(0);

            $table->date('issue_date');

            $table->date('due_date');

            $table->enum('status', [
                'unpaid',
                'partial',
                'paid',
                'cancelled',
            ])->default('unpaid');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
