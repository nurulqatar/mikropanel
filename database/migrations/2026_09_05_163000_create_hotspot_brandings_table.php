<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hotspot_brandings',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table->string(
                    'brand_name'
                )->default(
                    'MikroPanel Hotspot'
                );

                $table->string(
                    'portal_title'
                )->default(
                    'Welcome to WiFi'
                );

                $table->string(
                    'support_phone'
                )->nullable();

                $table->string(
                    'support_text'
                )->nullable();

                $table->string(
                    'primary_color',
                    20
                )->default(
                    '#0891b2'
                );

                $table->text(
                    'terms_text'
                )->nullable();

                $table->boolean(
                    'show_price'
                )->default(true);

                $table->boolean(
                    'show_qr'
                )->default(true);

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hotspot_brandings'
        );
    }
};
