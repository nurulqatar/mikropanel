<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'clients',
            function (Blueprint $table) {
                $table
                    ->string(
                        'profile_image_path'
                    )
                    ->nullable()
                    ->after(
                        'passport_image_path'
                    );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'clients',
            function (Blueprint $table) {
                $table->dropColumn(
                    'profile_image_path'
                );
            }
        );
    }
};
