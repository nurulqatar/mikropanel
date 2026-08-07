<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (
            Blueprint $table
        ): void {
            $table->string('role', 20)
                ->default('operator')
                ->after('password');

            $table->json('permissions')
                ->nullable()
                ->after('role');

            $table->boolean('is_active')
                ->default(true)
                ->after('permissions');
        });

        /*
         * বর্তমানে যেসব user আছে তারা lock out
         * হবে না। সবাইকে initial admin করা হবে।
         */
        DB::table('users')->update([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (
            Blueprint $table
        ): void {
            $table->dropColumn([
                'role',
                'permissions',
                'is_active',
            ]);
        });
    }
};
