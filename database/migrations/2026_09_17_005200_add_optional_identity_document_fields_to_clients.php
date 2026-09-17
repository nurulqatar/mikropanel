<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table
                ->string('qatar_id_number')
                ->nullable()
                ->after('identity_number');

            $table
                ->date('qatar_id_expiry_date')
                ->nullable()
                ->after('document_expiry_date');

            $table
                ->string('occupation')
                ->nullable()
                ->after('qatar_id_expiry_date');

            $table
                ->string('passport_number')
                ->nullable()
                ->after('occupation');

            $table
                ->date('passport_expiry_date')
                ->nullable()
                ->after('passport_number');

            $table
                ->string('document_serial_number')
                ->nullable()
                ->after('passport_expiry_date');

            $table
                ->string('residency_type')
                ->nullable()
                ->after('document_serial_number');

            $table
                ->string('employer')
                ->nullable()
                ->after('residency_type');

            $table
                ->string('place_of_birth')
                ->nullable()
                ->after('employer');

            $table
                ->date('passport_issue_date')
                ->nullable()
                ->after('place_of_birth');

            $table
                ->string('issuing_country')
                ->nullable()
                ->after('passport_issue_date');

            $table
                ->string('issuing_authority')
                ->nullable()
                ->after('issuing_country');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'qatar_id_number',
                'qatar_id_expiry_date',
                'occupation',
                'passport_number',
                'passport_expiry_date',
                'document_serial_number',
                'residency_type',
                'employer',
                'place_of_birth',
                'passport_issue_date',
                'issuing_country',
                'issuing_authority',
            ]);
        });
    }
};
