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
            function (Blueprint $table): void {
                if (
                    !Schema::hasColumn(
                        'clients',
                        'identity_type'
                    )
                ) {
                    $table
                        ->string(
                            'identity_type',
                            30
                        )
                        ->nullable()
                        ->after('address');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'identity_number'
                    )
                ) {
                    $table
                        ->string(
                            'identity_number',
                            150
                        )
                        ->nullable()
                        ->index()
                        ->after('identity_type');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'identity_barcode'
                    )
                ) {
                    $table
                        ->text(
                            'identity_barcode'
                        )
                        ->nullable()
                        ->after('identity_number');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'nationality'
                    )
                ) {
                    $table
                        ->string(
                            'nationality',
                            120
                        )
                        ->nullable()
                        ->after('identity_barcode');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'date_of_birth'
                    )
                ) {
                    $table
                        ->date(
                            'date_of_birth'
                        )
                        ->nullable()
                        ->after('nationality');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'gender'
                    )
                ) {
                    $table
                        ->string(
                            'gender',
                            20
                        )
                        ->nullable()
                        ->after('date_of_birth');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'document_expiry_date'
                    )
                ) {
                    $table
                        ->date(
                            'document_expiry_date'
                        )
                        ->nullable()
                        ->after('gender');
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'last_recharge_date'
                    )
                ) {
                    $table
                        ->date(
                            'last_recharge_date'
                        )
                        ->nullable()
                        ->index()
                        ->after(
                            'document_expiry_date'
                        );
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'imported_at'
                    )
                ) {
                    $table
                        ->timestamp(
                            'imported_at'
                        )
                        ->nullable()
                        ->after(
                            'last_recharge_date'
                        );
                }

                if (
                    !Schema::hasColumn(
                        'clients',
                        'import_batch_uuid'
                    )
                ) {
                    $table
                        ->string(
                            'import_batch_uuid',
                            36
                        )
                        ->nullable()
                        ->index()
                        ->after('imported_at');
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'clients',
            function (Blueprint $table): void {
                foreach ([
                    'identity_type',
                    'identity_number',
                    'identity_barcode',
                    'nationality',
                    'date_of_birth',
                    'gender',
                    'document_expiry_date',
                    'last_recharge_date',
                    'imported_at',
                    'import_batch_uuid',
                ] as $column) {
                    if (
                        Schema::hasColumn(
                            'clients',
                            $column
                        )
                    ) {
                        $table->dropColumn(
                            $column
                        );
                    }
                }
            }
        );
    }
};
