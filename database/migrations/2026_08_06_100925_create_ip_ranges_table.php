<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('ip_ranges', function (Blueprint $table) {

        $table->id();

        $table->foreignId('router_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->string('name');

        $table->string('interface');

        $table->string('network');

        $table->string('gateway');

        $table->string('dns_server')->nullable();

        $table->string('start_ip');

        $table->string('end_ip');

        $table->boolean('enabled')->default(true);

        $table->timestamps();
    });
}
    public function down(): void
    {
        Schema::dropIfExists('ip_ranges');
    }
};
