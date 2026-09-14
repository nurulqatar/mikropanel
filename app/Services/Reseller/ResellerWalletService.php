<?php

namespace App\Services\Reseller;

use App\Models\Reseller;
use App\Models\ResellerWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResellerWalletService
{
    public function credit(
        Reseller $reseller,
        float $amount,
        string $type,
        ?int $createdBy = null,
        ?string $paymentMethod = null,
        ?string $reference = null,
        ?string $notes = null
    ): ResellerWalletTransaction {
        return $this->post(
            $reseller,
            $amount,
            'credit',
            $type,
            $createdBy,
            $paymentMethod,
            $reference,
            $notes
        );
    }

    public function debit(
        Reseller $reseller,
        float $amount,
        string $type,
        ?int $createdBy = null,
        ?string $paymentMethod = null,
        ?string $reference = null,
        ?string $notes = null
    ): ResellerWalletTransaction {
        return $this->post(
            $reseller,
            $amount,
            'debit',
            $type,
            $createdBy,
            $paymentMethod,
            $reference,
            $notes
        );
    }

    private function post(
        Reseller $reseller,
        float $amount,
        string $direction,
        string $type,
        ?int $createdBy,
        ?string $paymentMethod,
        ?string $reference,
        ?string $notes
    ): ResellerWalletTransaction {
        $amount = round(
            $amount,
            2
        );

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Amount must be greater than zero.',
            ]);
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
                'Invalid wallet direction.'
            );
        }

        return DB::transaction(
            function () use (
                $reseller,
                $amount,
                $direction,
                $type,
                $createdBy,
                $paymentMethod,
                $reference,
                $notes
            ): ResellerWalletTransaction {
                $locked =
                    Reseller::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $reseller->id
                        );

                $before = round(
                    (float)
                    $locked
                        ->wallet_balance,
                    2
                );

                if (
                    $direction === 'debit'
                    && $amount > $before
                ) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Insufficient reseller wallet balance.',
                    ]);
                }

                $after =
                    $direction === 'credit'
                        ? round(
                            $before
                            + $amount,
                            2
                        )
                        : round(
                            $before
                            - $amount,
                            2
                        );

                $locked->forceFill([
                    'wallet_balance' =>
                        $after,
                ])->save();

                return ResellerWalletTransaction::create([
                    'transaction_no' =>
                        $this
                            ->transactionNumber(),

                    'reseller_id' =>
                        $locked->id,

                    'type' =>
                        $type,

                    'direction' =>
                        $direction,

                    'amount' =>
                        $amount,

                    'balance_before' =>
                        $before,

                    'balance_after' =>
                        $after,

                    'payment_method' =>
                        $paymentMethod,

                    'reference' =>
                        $reference,

                    'notes' =>
                        $notes,

                    'created_by' =>
                        $createdBy,
                ]);
            }
        );
    }

    private function transactionNumber(): string
    {
        do {
            $number =
                'RWL-'
                . now(
                    'Asia/Qatar'
                )->format(
                    'YmdHis'
                )
                . '-'
                . Str::upper(
                    Str::random(6)
                );
        } while (
            ResellerWalletTransaction::query()
                ->where(
                    'transaction_no',
                    $number
                )
                ->exists()
        );

        return $number;
    }
}
