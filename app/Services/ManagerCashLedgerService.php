<?php

namespace App\Services;

use App\Models\ClientRefund;
use App\Models\Expense;
use App\Models\ManagerCashHandover;
use App\Models\ManagerCashLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ManagerCashLedgerService
{
    private array $managerCache = [];

    public function recordPayment(
        Payment $payment
    ): ?ManagerCashLedgerEntry {
        if (
            !$this->isCash(
                $payment->payment_method
            )
        ) {
            return null;
        }

        $manager =
            $this->manager(
                $payment->received_by
            );

        if (!$manager) {
            return null;
        }

        return $this->record(
            manager: $manager,
            zoneId: $payment->zone_id,
            entryDate:
                $payment->payment_date
                    ?->toDateString()
                ?? now(
                    'Asia/Qatar'
                )->toDateString(),
            direction: 'credit',
            entryType: 'collection',
            amount: (float) $payment->amount,
            sourceType: 'payment',
            sourceId: (int) $payment->id,
            reference:
                'PAY-'
                . $payment->id,
            notes:
                'Cash collection from client payment.',
            createdBy:
                $payment->received_by
        );
    }

    public function reversePayment(
        Payment $payment
    ): ?ManagerCashLedgerEntry {
        $original =
            ManagerCashLedgerEntry::query()
                ->where(
                    'entry_type',
                    'collection'
                )
                ->where(
                    'source_type',
                    'payment'
                )
                ->where(
                    'source_id',
                    $payment->id
                )
                ->first();

        if (!$original) {
            return null;
        }

        $manager =
            $this->manager(
                $original->manager_id
            );

        if (!$manager) {
            return null;
        }

        return $this->record(
            manager: $manager,
            zoneId: $original->zone_id,
            entryDate:
                now(
                    'Asia/Qatar'
                )->toDateString(),
            direction: 'debit',
            entryType:
                'collection_reversal',
            amount:
                (float)
                $original->amount,
            sourceType: 'payment',
            sourceId: (int) $payment->id,
            reference:
                'PAY-'
                . $payment->id,
            notes:
                'Reversal because payment was deleted.',
            createdBy:
                Auth::id()
        );
    }

    /*
     * CASH_REFUND_LEDGER_V2
     *
     * A refund against a Cash payment removes
     * physical cash from the same Manager account.
     */
    public function recordRefund(
        ClientRefund $refund
    ): ?ManagerCashLedgerEntry {
        if (
            (float) $refund->amount <= 0
            || !$refund->payment_id
        ) {
            return null;
        }

        $payment =
            Payment::withoutGlobalScopes()
                ->find(
                    $refund->payment_id
                );

        if (
            !$payment
            || !$this->isCash(
                $payment->payment_method
            )
        ) {
            return null;
        }

        /*
         * Prefer the Manager that owns the
         * original payment collection entry.
         */
        $collection =
            ManagerCashLedgerEntry::query()
                ->where(
                    'entry_type',
                    'collection'
                )
                ->where(
                    'source_type',
                    'payment'
                )
                ->where(
                    'source_id',
                    $payment->id
                )
                ->first();

        $manager =
            $collection
                ? $this->manager(
                    $collection->manager_id
                )
                : $this->manager(
                    $refund->refunded_by
                );

        if (!$manager) {
            return null;
        }

        $refundDate =
            $refund->refund_date
                ? \Illuminate\Support\Carbon::parse(
                    $refund->refund_date
                )->toDateString()
                : now(
                    'Asia/Qatar'
                )->toDateString();

        return $this->record(
            manager: $manager,
            zoneId:
                $refund->zone_id
                ?: $payment->zone_id,
            entryDate:
                $refundDate,
            direction:
                'debit',
            entryType:
                'refund',
            amount:
                (float) $refund->amount,
            sourceType:
                'client_refund',
            sourceId:
                (int) $refund->id,
            reference:
                'REFUND-'
                . $refund->id,
            notes:
                'Cash returned to client.',
            createdBy:
                $refund->refunded_by
        );
    }


    public function recordExpense(
        Expense $expense
    ): ?ManagerCashLedgerEntry {
        if (
            !$this->isCash(
                $expense->payment_method
            )
            || $expense->approval_status
                === 'rejected'
        ) {
            return null;
        }

        $manager =
            $this->manager(
                $expense->created_by
            );

        if (!$manager) {
            return null;
        }

        return $this->record(
            manager: $manager,
            zoneId: $expense->zone_id,
            entryDate:
                $expense->expense_date
                    ?->toDateString()
                ?? now(
                    'Asia/Qatar'
                )->toDateString(),
            direction: 'debit',
            entryType: 'expense',
            amount: (float) $expense->amount,
            sourceType: 'expense',
            sourceId: (int) $expense->id,
            reference:
                'EXP-'
                . $expense->id,
            notes:
                'Manager cash expense.',
            createdBy:
                $expense->created_by
        );
    }

    public function reverseExpense(
        Expense $expense,
        ?string $reason = null
    ): ?ManagerCashLedgerEntry {
        $original =
            ManagerCashLedgerEntry::query()
                ->where(
                    'entry_type',
                    'expense'
                )
                ->where(
                    'source_type',
                    'expense'
                )
                ->where(
                    'source_id',
                    $expense->id
                )
                ->first();

        if (!$original) {
            return null;
        }

        $manager =
            $this->manager(
                $original->manager_id
            );

        if (!$manager) {
            return null;
        }

        return $this->record(
            manager: $manager,
            zoneId: $original->zone_id,
            entryDate:
                now(
                    'Asia/Qatar'
                )->toDateString(),
            direction: 'credit',
            entryType:
                'expense_reversal',
            amount:
                (float)
                $original->amount,
            sourceType: 'expense',
            sourceId: (int) $expense->id,
            reference:
                'EXP-'
                . $expense->id,
            notes:
                $reason
                ?: 'Expense cash effect reversed.',
            createdBy:
                Auth::id()
        );
    }

    public function recordApprovedHandover(
        ManagerCashHandover $handover,
        User $reviewer
    ): ManagerCashLedgerEntry {
        $manager =
            $this->manager(
                $handover->manager_id
            );

        if (
            !$manager
            || (int)
                $manager->reseller_id
                !== (int)
                $handover->reseller_id
        ) {
            throw new \RuntimeException(
                'Invalid manager for cash handover.'
            );
        }

        return $this->record(
            manager: $manager,
            zoneId: null,
            entryDate:
                $handover
                    ->handover_date
                    ?->toDateString()
                ?? now(
                    'Asia/Qatar'
                )->toDateString(),
            direction: 'debit',
            entryType: 'handover',
            amount:
                (float)
                $handover->amount,
            sourceType: 'handover',
            sourceId:
                (int)
                $handover->id,
            reference:
                'HANDOVER-'
                . $handover->id,
            notes:
                'Cash handed over to reseller admin.',
            createdBy:
                $reviewer->id
        );
    }

    public function balance(
        int|User $manager
    ): float {
        $managerId =
            $manager instanceof User
                ? (int) $manager->id
                : (int) $manager;

        $credit =
            (float)
            ManagerCashLedgerEntry::query()
                ->where(
                    'manager_id',
                    $managerId
                )
                ->where(
                    'direction',
                    'credit'
                )
                ->sum(
                    'amount'
                );

        $debit =
            (float)
            ManagerCashLedgerEntry::query()
                ->where(
                    'manager_id',
                    $managerId
                )
                ->where(
                    'direction',
                    'debit'
                )
                ->sum(
                    'amount'
                );

        return round(
            $credit - $debit,
            2
        );
    }

    /*
     * MANAGER_CASH_BACKFILL_WITH_REFUNDS_V2
     */
    public function backfillExisting(): array
    {
        $beforeCollections =
            ManagerCashLedgerEntry::query()
                ->where(
                    'entry_type',
                    'collection'
                )
                ->count();

        $beforeExpenses =
            ManagerCashLedgerEntry::query()
                ->where(
                    'entry_type',
                    'expense'
                )
                ->count();

        $beforeRefunds =
            ManagerCashLedgerEntry::query()
                ->where(
                    'entry_type',
                    'refund'
                )
                ->count();

        Payment::withoutGlobalScopes()
            ->whereNotNull(
                'received_by'
            )
            ->whereRaw(
                'LOWER(TRIM(payment_method)) = ?',
                ['cash']
            )
            ->orderBy('id')
            ->chunkById(
                200,
                function ($payments): void {
                    foreach (
                        $payments
                        as $payment
                    ) {
                        $this->recordPayment(
                            $payment
                        );
                    }
                }
            );

        Expense::withoutGlobalScopes()
            ->whereNotNull(
                'created_by'
            )
            ->whereRaw(
                'LOWER(TRIM(payment_method)) = ?',
                ['cash']
            )
            ->where(
                'approval_status',
                '!=',
                'rejected'
            )
            ->orderBy('id')
            ->chunkById(
                200,
                function ($expenses): void {
                    foreach (
                        $expenses
                        as $expense
                    ) {
                        $this->recordExpense(
                            $expense
                        );
                    }
                }
            );

        ClientRefund::withoutGlobalScopes()
            ->where(
                'amount',
                '>',
                0
            )
            ->whereNotNull(
                'payment_id'
            )
            ->orderBy('id')
            ->chunkById(
                200,
                function ($refunds): void {
                    foreach (
                        $refunds
                        as $refund
                    ) {
                        $this->recordRefund(
                            $refund
                        );
                    }
                }
            );

        return [
            'collections_created' =>
                ManagerCashLedgerEntry::query()
                    ->where(
                        'entry_type',
                        'collection'
                    )
                    ->count()
                - $beforeCollections,

            'expenses_created' =>
                ManagerCashLedgerEntry::query()
                    ->where(
                        'entry_type',
                        'expense'
                    )
                    ->count()
                - $beforeExpenses,

            'refunds_created' =>
                ManagerCashLedgerEntry::query()
                    ->where(
                        'entry_type',
                        'refund'
                    )
                    ->count()
                - $beforeRefunds,
        ];
    }


    private function record(
        User $manager,
        ?int $zoneId,
        string $entryDate,
        string $direction,
        string $entryType,
        float $amount,
        string $sourceType,
        int $sourceId,
        ?string $reference,
        ?string $notes,
        ?int $createdBy
    ): ManagerCashLedgerEntry {
        $amount =
            round(
                abs($amount),
                2
            );

        if ($amount <= 0) {
            throw new \InvalidArgumentException(
                'Cash ledger amount must be greater than zero.'
            );
        }

        if (
            !in_array(
                $direction,
                [
                    'credit',
                    'debit',
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid cash ledger direction.'
            );
        }

        return ManagerCashLedgerEntry::query()
            ->firstOrCreate(
                [
                    'manager_id' =>
                        $manager->id,

                    'source_type' =>
                        $sourceType,

                    'source_id' =>
                        $sourceId,

                    'entry_type' =>
                        $entryType,
                ],
                [
                    'reseller_id' =>
                        $manager->reseller_id,

                    'zone_id' =>
                        $zoneId,

                    'entry_date' =>
                        $entryDate,

                    'direction' =>
                        $direction,

                    'amount' =>
                        $amount,

                    'reference' =>
                        $reference,

                    'notes' =>
                        $notes,

                    'created_by' =>
                        $createdBy,
                ]
            );
    }

    /*
     * OPERATOR_MANAGER_CASH_RESOLUTION_V2
     *
     * Manager action:
     *   -> own Manager Cash.
     *
     * Normal Operator action:
     *   -> reseller's single active Manager.
     *
     * Owner cash is not silently mixed into
     * Manager physical cash.
     */
    private function manager(
        ?int $userId
    ): ?User {
        $userId =
            (int) (
                $userId
                ?? 0
            );

        if ($userId <= 0) {
            return null;
        }

        if (
            array_key_exists(
                $userId,
                $this->managerCache
            )
        ) {
            return $this
                ->managerCache[
                    $userId
                ];
        }

        $user =
            User::query()
                ->whereKey(
                    $userId
                )
                ->first();

        if (
            !$user
            || !$user->reseller_id
        ) {
            $this->managerCache[
                $userId
            ] = null;

            return null;
        }

        /*
         * Direct Manager transaction.
         */
        if ($user->isManager()) {
            $this->managerCache[
                $userId
            ] = $user;

            return $user;
        }

        /*
         * Only normal Operators are mapped
         * automatically to Manager Cash.
         */
        if (!$user->isOperator()) {
            $this->managerCache[
                $userId
            ] = null;

            return null;
        }

        $managers =
            User::query()
                ->where(
                    'reseller_id',
                    $user->reseller_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    function ($query): void {
                        $query
                            ->where(
                                'role',
                                'manager'
                            )
                            ->orWhere(
                                function (
                                    $query
                                ): void {
                                    $query
                                        ->where(
                                            'role',
                                            'operator'
                                        )
                                        ->where(
                                            'staff_role',
                                            'manager'
                                        );
                                }
                            );
                    }
                )
                ->orderBy('id')
                ->limit(2)
                ->get();

        if (
            $managers->count() !== 1
        ) {
            throw new \RuntimeException(
                'Cash accounting requires exactly one active Manager for this reseller.'
            );
        }

        $manager =
            $managers->first();

        $this->managerCache[
            $userId
        ] = $manager;

        return $manager;
    }


    private function isCash(
        ?string $method
    ): bool {
        return strtolower(
            trim(
                (string)
                $method
            )
        ) === 'cash';
    }
}
