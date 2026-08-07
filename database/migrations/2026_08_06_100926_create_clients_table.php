<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

	    $table->unsignedBigInteger('router_id');
	    $table->unsignedBigInteger('package_id');

            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->date('expiry_date')->nullable();

            $table->boolean('enabled')->default(true);
            $table->boolean('connected')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
