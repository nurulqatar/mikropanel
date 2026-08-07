<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('identity')->nullable()->after('password');
            $table->string('routeros_version')->nullable()->after('identity');
            $table->string('board_name')->nullable()->after('routeros_version');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'identity',
                'routeros_version',
                'board_name',
            ]);
        });
    }
};
