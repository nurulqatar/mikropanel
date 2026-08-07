<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('host');
            $table->integer('api_port')->default(8728);

            $table->string('username');

            // Encrypted password
            $table->text('password');

            $table->boolean('use_ssl')->default(false);

            $table->boolean('enabled')->default(true);

            $table->boolean('connected')->default(false);

            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
