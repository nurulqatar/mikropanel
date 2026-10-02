<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable(
                'hotspot_sellers'
            )
        ) {
            Schema::create(
                'hotspot_sellers',
                function (
                    Blueprint $table
                ): void {
                    $table->id();

                    $table->foreignId(
                        'reseller_id'
                    )
                        ->nullable()
                        ->constrained(
                            'resellers'
                        )
                        ->nullOnDelete();

                    $table->string(
                        'code',
                        40
                    )->unique();

                    $table->string(
                        'name'
                    );

                    $table->string(
                        'phone',
                        100
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->boolean(
                        'active'
                    )->default(true);

                    $table->timestamps();

                    $table->index([
                        'reseller_id',
                        'active',
                    ]);
                }
            );
        }

        if (
            !Schema::hasTable(
                'hotspot_seller_collections'
            )
        ) {
            Schema::create(
                'hotspot_seller_collections',
                function (
                    Blueprint $table
                ): void {
                    $table->id();

                    $table->foreignId(
                        'reseller_id'
                    )
                        ->nullable()
                        ->constrained(
                            'resellers'
                        )
                        ->nullOnDelete();

                    $table->foreignId(
                        'hotspot_seller_id'
                    )
                        ->constrained(
                            'hotspot_sellers'
                        )
                        ->cascadeOnDelete();

                    $table->decimal(
                        'amount',
                        12,
                        2
                    );

                    $table->timestamp(
                        'collected_at'
                    );

                    $table->string(
                        'payment_method',
                        100
                    )->default('Cash');

                    $table->string(
                        'reference',
                        255
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->foreignId(
                        'collected_by'
                    )
                        ->nullable()
                        ->constrained(
                            'users'
                        )
                        ->nullOnDelete();

                    $table->timestamps();

                    $table->index([
                        'hotspot_seller_id',
                        'collected_at',
                    ]);
                }
            );
        }

        if (
            !Schema::hasColumn(
                'hotspot_batches',
                'hotspot_seller_id'
            )
        ) {
            Schema::table(
                'hotspot_batches',
                function (
                    Blueprint $table
                ): void {
                    $table->foreignId(
                        'hotspot_seller_id'
                    )
                        ->nullable()
                        ->after(
                            'hotspot_plan_id'
                        )
                        ->constrained(
                            'hotspot_sellers'
                        )
                        ->nullOnDelete();
                }
            );
        }

        if (
            !Schema::hasColumn(
                'hotspot_vouchers',
                'hotspot_seller_id'
            )
        ) {
            Schema::table(
                'hotspot_vouchers',
                function (
                    Blueprint $table
                ): void {
                    $table->foreignId(
                        'hotspot_seller_id'
                    )
                        ->nullable()
                        ->after(
                            'hotspot_batch_id'
                        )
                        ->constrained(
                            'hotspot_sellers'
                        )
                        ->nullOnDelete();

                    $table->index([
                        'hotspot_seller_id',
                        'sold_at',
                    ]);
                }
            );
        }

        /*
         * Snapshot which seller owned the sale.
         * Reassignment of an unsold voucher later
         * can never rewrite historical sales.
         */
        if (
            !Schema::hasColumn(
                'hotspot_invoices',
                'hotspot_seller_id'
            )
        ) {
            Schema::table(
                'hotspot_invoices',
                function (
                    Blueprint $table
                ): void {
                    $table->foreignId(
                        'hotspot_seller_id'
                    )
                        ->nullable()
                        ->after(
                            'hotspot_voucher_id'
                        )
                        ->constrained(
                            'hotspot_sellers'
                        )
                        ->nullOnDelete();

                    $table->index([
                        'hotspot_seller_id',
                        'invoice_type',
                        'status',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'hotspot_invoices',
                'hotspot_seller_id'
            )
        ) {
            Schema::table(
                'hotspot_invoices',
                function (
                    Blueprint $table
                ): void {
                    $table->dropConstrainedForeignId(
                        'hotspot_seller_id'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'hotspot_vouchers',
                'hotspot_seller_id'
            )
        ) {
            Schema::table(
                'hotspot_vouchers',
                function (
                    Blueprint $table
                ): void {
                    $table->dropConstrainedForeignId(
                        'hotspot_seller_id'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'hotspot_batches',
                'hotspot_seller_id'
            )
        ) {
            Schema::table(
                'hotspot_batches',
                function (
                    Blueprint $table
                ): void {
                    $table->dropConstrainedForeignId(
                        'hotspot_seller_id'
                    );
                }
            );
        }

        Schema::dropIfExists(
            'hotspot_seller_collections'
        );

        Schema::dropIfExists(
            'hotspot_sellers'
        );
    }
};
