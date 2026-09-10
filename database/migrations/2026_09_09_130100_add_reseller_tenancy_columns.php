<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantTables = [
        'clients',
        'routers',
        'packages',
        'ip_ranges',
        'invoices',
        'payments',
        'expenses',
        'activity_logs',
        'client_monthly_usages',
        'client_router_bindings',
        'client_custom_fields',
        'hotspot_servers',
        'hotspot_plans',
        'hotspot_batches',
        'hotspot_vouchers',
        'hotspot_invoices',
        'hotspot_payments',
        'hotspot_sessions',
    ];

    public function up(): void
    {
        if (
            Schema::hasTable('users')
            && !Schema::hasColumn(
                'users',
                'reseller_id'
            )
        ) {
            Schema::table(
                'users',
                function (
                    Blueprint $table
                ): void {
                    $table->foreignId(
                        'reseller_id'
                    )
                        ->nullable()
                        ->after('id')
                        ->constrained(
                            'resellers'
                        )
                        ->nullOnDelete();

                    $table->index([
                        'reseller_id',
                        'is_active',
                    ]);
                }
            );
        }

        if (
            Schema::hasTable('users')
            && !Schema::hasColumn(
                'users',
                'is_super_admin'
            )
        ) {
            Schema::table(
                'users',
                function (
                    Blueprint $table
                ): void {
                    $table->boolean(
                        'is_super_admin'
                    )
                        ->default(false)
                        ->after('role');

                    $table->index(
                        'is_super_admin'
                    );
                }
            );
        }

        /*
         * All current platform admins are the
         * initial Super Admin accounts.
         */
        DB::table('users')
            ->where(
                'role',
                'admin'
            )
            ->update([
                'is_super_admin' =>
                    true,
            ]);

        foreach (
            $this->tenantTables
            as $tableName
        ) {
            if (
                !Schema::hasTable(
                    $tableName
                )
                || Schema::hasColumn(
                    $tableName,
                    'reseller_id'
                )
            ) {
                continue;
            }

            Schema::table(
                $tableName,
                function (
                    Blueprint $table
                ): void {
                    $table->foreignId(
                        'reseller_id'
                    )
                        ->nullable()
                        ->constrained(
                            'resellers'
                        )
                        ->restrictOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        foreach (
            array_reverse(
                $this->tenantTables
            )
            as $tableName
        ) {
            if (
                Schema::hasTable(
                    $tableName
                )
                && Schema::hasColumn(
                    $tableName,
                    'reseller_id'
                )
            ) {
                Schema::table(
                    $tableName,
                    function (
                        Blueprint $table
                    ): void {
                        $table
                            ->dropConstrainedForeignId(
                                'reseller_id'
                            );
                    }
                );
            }
        }

        if (
            Schema::hasTable('users')
            && Schema::hasColumn(
                'users',
                'reseller_id'
            )
        ) {
            Schema::table(
                'users',
                function (
                    Blueprint $table
                ): void {
                    $table->dropIndex([
                        'reseller_id',
                        'is_active',
                    ]);

                    $table
                        ->dropConstrainedForeignId(
                            'reseller_id'
                        );
                }
            );
        }

        if (
            Schema::hasTable('users')
            && Schema::hasColumn(
                'users',
                'is_super_admin'
            )
        ) {
            Schema::table(
                'users',
                function (
                    Blueprint $table
                ): void {
                    $table->dropIndex([
                        'is_super_admin',
                    ]);

                    $table->dropColumn(
                        'is_super_admin'
                    );
                }
            );
        }
    }
};
