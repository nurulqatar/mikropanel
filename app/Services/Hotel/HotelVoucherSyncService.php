<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelVoucher;
use App\Models\Hotel\HotelVoucherRouterSync;
use Throwable;

class HotelVoucherSyncService
{
    public function __construct(
        private readonly HotelMikroTikVoucherService $mikrotik
    ) {
    }

    public function syncVoucher(
        HotelVoucher $voucher,
        string $operation = 'auto'
    ): array {
        $operation =
            $this->resolveOperation(
                $voucher,
                $operation
            );

        $routers =
            HotelRouter::query()
                ->where(
                    'hotel_id',
                    $voucher->hotel_id
                )
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('id')
                ->get();

        $summary = [
            'voucher_id' =>
                $voucher->id,

            'operation' =>
                $operation,

            'total' =>
                $routers->count(),

            'success' =>
                0,

            'failed' =>
                0,

            'routers' =>
                [],
        ];

        foreach ($routers as $router) {
            $sync =
                HotelVoucherRouterSync::query()
                    ->updateOrCreate(
                        [
                            'hotel_voucher_id' =>
                                $voucher->id,

                            'hotel_router_id' =>
                                $router->id,
                        ],
                        [
                            'status' =>
                                'pending',

                            'operation' =>
                                $operation,

                            'last_error' =>
                                null,
                        ]
                    );

            $sync->increment(
                'attempts'
            );

            $sync->forceFill([
                'last_attempted_at' =>
                    now(),

                'operation' =>
                    $operation,
            ])->save();

            try {
                $result =
                    $operation === 'expire'
                        ? $this->mikrotik
                            ->expire(
                                $router,
                                $voucher
                            )
                        : $this->mikrotik
                            ->upsert(
                                $router,
                                $voucher
                            );

                $success =
                    (bool) (
                        $result['success']
                        ?? false
                    );

                if ($success) {
                    $sync->forceFill([
                        'status' =>
                            $operation === 'expire'
                                ? 'expired'
                                : 'synced',

                        'router_user_id' =>
                            $result[
                                'router_user_id'
                            ]
                            ?? $sync
                                ->router_user_id,

                        'synced_at' =>
                            $operation === 'upsert'
                                ? now()
                                : $sync->synced_at,

                        'expired_at' =>
                            $operation === 'expire'
                                ? now()
                                : null,

                        'last_error' =>
                            null,
                    ])->save();

                    $summary['success']++;
                } else {
                    $message =
                        (string) (
                            $result['message']
                            ?? 'Unknown MikroTik synchronization error.'
                        );

                    $sync->forceFill([
                        'status' =>
                            'failed',

                        'last_error' =>
                            mb_substr(
                                $message,
                                0,
                                2000
                            ),
                    ])->save();

                    $summary['failed']++;
                }

                $summary['routers'][] = [
                    'router_id' =>
                        $router->id,

                    'status' =>
                        $success
                            ? (
                                $operation === 'expire'
                                    ? 'expired'
                                    : 'synced'
                            )
                            : 'failed',
                ];
            } catch (Throwable $e) {
                $sync->forceFill([
                    'status' =>
                        'failed',

                    'last_error' =>
                        mb_substr(
                            $e->getMessage(),
                            0,
                            2000
                        ),
                ])->save();

                $summary['failed']++;

                $summary['routers'][] = [
                    'router_id' =>
                        $router->id,

                    'status' =>
                        'failed',
                ];
            }
        }

        return $summary;
    }

    private function resolveOperation(
        HotelVoucher $voucher,
        string $operation
    ): string {
        if (
            in_array(
                $operation,
                [
                    'upsert',
                    'expire',
                ],
                true
            )
        ) {
            return $operation;
        }

        if (
            $voucher->expires_at
            && $voucher->expires_at->lte(now())
        ) {
            return 'expire';
        }

        return 'upsert';
    }
}
