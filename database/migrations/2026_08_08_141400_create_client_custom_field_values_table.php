<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_custom_field_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->foreignId('custom_field_id')
                ->constrained('client_custom_fields')
                ->cascadeOnDelete();

            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique([
                'client_id',
                'custom_field_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_custom_field_values');
    }
};
