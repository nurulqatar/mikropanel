<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reseller_notifications',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'reseller_id'
                )
                    ->constrained(
                        'resellers'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->string(
                    'type',
                    100
                );

                $table->string(
                    'level',
                    30
                )->default('info');

                $table->string(
                    'title'
                );

                $table->text(
                    'message'
                );

                $table->string(
                    'dedupe_key'
                )
                    ->nullable()
                    ->unique();

                $table->json(
                    'data'
                )->nullable();

                $table->timestamp(
                    'read_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'reseller_id',
                    'read_at',
                ]);

                $table->index([
                    'reseller_id',
                    'type',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reseller_notifications'
        );
    }
};
