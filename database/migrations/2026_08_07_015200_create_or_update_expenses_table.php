<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->date('expense_date');
                $table->string('category', 100);
                $table->string('title');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 100)
                    ->default('Cash');
                $table->text('notes')->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index('expense_date');
                $table->index('category');
            });

            return;
        }

        /*
         * Table আগে থেকে থাকলে শুধু missing column যোগ হবে।
         */
        $columns = array_flip(
            Schema::getColumnListing('expenses')
        );

        Schema::table('expenses', function (Blueprint $table) use ($columns) {
            if (!isset($columns['expense_date'])) {
                $table->date('expense_date')->nullable();
            }

            if (!isset($columns['category'])) {
                $table->string('category', 100)
                    ->default('Other');
            }

            if (!isset($columns['title'])) {
                $table->string('title')
                    ->default('Expense');
            }

            if (!isset($columns['amount'])) {
                $table->decimal('amount', 12, 2)
                    ->default(0);
            }

            if (!isset($columns['payment_method'])) {
                $table->string('payment_method', 100)
                    ->default('Cash');
            }

            if (!isset($columns['notes'])) {
                $table->text('notes')->nullable();
            }

            if (!isset($columns['created_by'])) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!isset($columns['created_at'])) {
                $table->timestamp('created_at')->nullable();
            }

            if (!isset($columns['updated_at'])) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        /*
         * Non-destructive rollback:
         * Expense data automatic delete করা হবে না।
         */
    }
};
