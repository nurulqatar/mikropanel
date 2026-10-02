<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'manager_cash_ledger_entries',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->unsignedBigInteger(
                        'reseller_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'manager_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'zone_id'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->date(
                        'entry_date'
                    )
                    ->index();

                $table->string(
                    'direction',
                    10
                );

                $table->string(
                    'entry_type',
                    60
                );

                $table->decimal(
                    'amount',
                    14,
                    2
                );

                $table->string(
                    'source_type',
                    60
                );

                $table->unsignedBigInteger(
                    'source_id'
                );

                $table
                    ->string(
                        'reference',
                        191
                    )
                    ->nullable();

                $table
                    ->text(
                        'notes'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'created_by'
                    )
                    ->nullable()
                    ->index();

                $table->timestamps();

                $table->index(
                    [
                        'manager_id',
                        'entry_date',
                    ],
                    'idx_mcl_manager_date'
                );

                $table->index(
                    [
                        'reseller_id',
                        'entry_date',
                    ],
                    'idx_mcl_reseller_date'
                );

                /*
                 * Immutable source event can be posted
                 * only once per entry type.
                 */
                $table->unique(
                    [
                        'manager_id',
                        'source_type',
                        'source_id',
                        'entry_type',
                    ],
                    'uq_mcl_source_event'
                );
            }
        );

        Schema::create(
            'manager_cash_handovers',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->unsignedBigInteger(
                        'reseller_id'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'manager_id'
                    )
                    ->index();

                $table->decimal(
                    'amount',
                    14,
                    2
                );

                $table->date(
                    'handover_date'
                );

                $table->string(
                    'status',
                    20
                )->default(
                    'pending'
                );

                $table
                    ->text(
                        'notes'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'submitted_by'
                    )
                    ->index();

                $table
                    ->unsignedBigInteger(
                        'reviewed_by'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->timestamp(
                        'reviewed_at'
                    )
                    ->nullable();

                $table
                    ->text(
                        'review_notes'
                    )
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'manager_id',
                        'status',
                    ],
                    'idx_mch_manager_status'
                );

                $table->index(
                    [
                        'reseller_id',
                        'status',
                    ],
                    'idx_mch_reseller_status'
                );
            }
        );

        Schema::table(
            'expenses',
            function (Blueprint $table): void {
                $table
                    ->string(
                        'approval_status',
                        20
                    )
                    ->default(
                        'approved'
                    )
                    ->index();

                $table
                    ->timestamp(
                        'approved_at'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'reviewed_by'
                    )
                    ->nullable()
                    ->index();

                $table
                    ->timestamp(
                        'reviewed_at'
                    )
                    ->nullable();

                $table
                    ->text(
                        'rejection_reason'
                    )
                    ->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'expenses',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'approval_status',
                    'approved_at',
                    'reviewed_by',
                    'reviewed_at',
                    'rejection_reason',
                ]);
            }
        );

        Schema::dropIfExists(
            'manager_cash_handovers'
        );

        Schema::dropIfExists(
            'manager_cash_ledger_entries'
        );
    }
};
