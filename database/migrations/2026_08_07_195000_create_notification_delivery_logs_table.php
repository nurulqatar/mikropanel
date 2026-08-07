<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable(
                'notification_delivery_logs'
            )
        ) {
            return;
        }

        Schema::create(
            'notification_delivery_logs',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'event_key',
                    191
                );

                $table->string(
                    'channel',
                    32
                );

                $table->unsignedBigInteger(
                    'client_id'
                )->nullable();

                $table->string(
                    'recipient'
                )->nullable();

                $table->string(
                    'status',
                    40
                )->default(
                    'waiting'
                );

                $table->string(
                    'provider'
                )->nullable();

                $table->unsignedSmallInteger(
                    'attempts'
                )->default(0);

                $table->text(
                    'last_error'
                )->nullable();

                $table->json(
                    'payload'
                )->nullable();

                $table->timestamp(
                    'sent_at'
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'event_key',
                    'channel',
                ]);

                $table->index([
                    'status',
                    'channel',
                ]);

                $table->index(
                    'client_id'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'notification_delivery_logs'
        );
    }
};
