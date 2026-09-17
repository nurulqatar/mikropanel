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
                        'qatar_id_front_image_path'
                    )
                    ->nullable()
                    ->after(
                        'issuing_authority'
                    );

                $table
                    ->string(
                        'qatar_id_back_image_path'
                    )
                    ->nullable()
                    ->after(
                        'qatar_id_front_image_path'
                    );

                $table
                    ->string(
                        'passport_image_path'
                    )
                    ->nullable()
                    ->after(
                        'qatar_id_back_image_path'
                    );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'clients',
            function (Blueprint $table) {
                $table->dropColumn([
                    'qatar_id_front_image_path',
                    'qatar_id_back_image_path',
                    'passport_image_path',
                ]);
            }
        );
    }
};
