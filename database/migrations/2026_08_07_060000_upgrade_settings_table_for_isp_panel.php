<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (
                Blueprint $table
            ): void {
                $table->id();

                $table->string('group')
                    ->default('general');

                $table->string('key')->index();

                $table->longText('value')
                    ->nullable();

                $table->string('type')
                    ->default('string');

                $table->boolean('is_encrypted')
                    ->default(false);

                $table->text('description')
                    ->nullable();

                $table->timestamps();
            });

            return;
        }

        if (!Schema::hasColumn('settings', 'group')) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->string('group')
                    ->default('general')
                    ->after('id');
            });
        }

        if (!Schema::hasColumn('settings', 'key')) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->string('key')
                    ->nullable()
                    ->index();
            });
        }

        if (!Schema::hasColumn('settings', 'value')) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->longText('value')
                    ->nullable();
            });
        }

        if (!Schema::hasColumn('settings', 'type')) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->string('type')
                    ->default('string');
            });
        }

        if (
            !Schema::hasColumn(
                'settings',
                'is_encrypted'
            )
        ) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->boolean('is_encrypted')
                    ->default(false);
            });
        }

        if (
            !Schema::hasColumn(
                'settings',
                'description'
            )
        ) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->text('description')
                    ->nullable();
            });
        }

        if (
            !Schema::hasColumn(
                'settings',
                'created_at'
            )
        ) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->timestamp('created_at')
                    ->nullable();
            });
        }

        if (
            !Schema::hasColumn(
                'settings',
                'updated_at'
            )
        ) {
            Schema::table('settings', function (
                Blueprint $table
            ): void {
                $table->timestamp('updated_at')
                    ->nullable();
            });
        }
    }

    public function down(): void
    {
        /*
         * Existing settings যেন rollback-এর সময়
         * delete না হয়, তাই intentionally empty।
         */
    }
};
