<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('field_key', 120)->unique();
            $table->string('type', 30)->default('text');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('show_in_list')->default(false);
            $table->boolean('show_in_reports')->default(true);
            $table->boolean('show_in_invoice')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_custom_fields');
    }
};
