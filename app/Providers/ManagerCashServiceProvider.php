<?php

namespace App\Providers;

use App\Models\ClientRefund;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\ManagerCashLedgerService;
use Illuminate\Support\ServiceProvider;

class ManagerCashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(
        ManagerCashLedgerService $ledger
    ): void {
        /*
         * Customer cash collected by a Manager.
         */
        Payment::created(
            function (
                Payment $payment
            ) use (
                $ledger
            ): void {
                $ledger->recordPayment(
                    $payment
                );
            }
        );

        /*
         * Payment deletion never erases cash history.
         * It creates an opposite immutable entry.
         */
        Payment::deleted(
            function (
                Payment $payment
            ) use (
                $ledger
            ): void {
                $ledger->reversePayment(
                    $payment
                );
            }
        );

        /*
         * CASH_REFUND_LISTENER_V2
         *
         * Cash refund immediately reduces the
         * responsible Manager physical cash.
         */
        ClientRefund::created(
            function (
                ClientRefund $refund
            ) use (
                $ledger
            ): void {
                $ledger->recordRefund(
                    $refund
                );
            }
        );

        /*
         * Manager expense is auto-approved and
         * immediately reduces physical cash.
         */
        Expense::created(
            function (
                Expense $expense
            ) use (
                $ledger
            ): void {
                $ledger->recordExpense(
                    $expense
                );
            }
        );

        /*
         * Existing delete flow is financially safe:
         * cash impact is reversed instead of erased.
         */
        Expense::deleted(
            function (
                Expense $expense
            ) use (
                $ledger
            ): void {
                $ledger->reverseExpense(
                    $expense,
                    'Expense deleted; manager cash restored.'
                );
            }
        );
    }
}
