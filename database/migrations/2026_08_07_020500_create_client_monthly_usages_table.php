<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'client_monthly_usages',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('client_id')
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->date('usage_month');

                $table->unsignedBigInteger('upload_bytes')
                    ->default(0);

                $table->unsignedBigInteger('download_bytes')
                    ->default(0);

                $table->unsignedBigInteger('last_upload_counter')
                    ->default(0);

                $table->unsignedBigInteger('last_download_counter')
                    ->default(0);

                $table->timestamp('last_synced_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'client_id',
                    'usage_month',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'client_monthly_usages'
        );
    }
};
