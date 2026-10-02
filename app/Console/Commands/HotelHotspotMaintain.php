<?php

namespace App\Console\Commands;

use App\Jobs\Hotel\SyncHotelVoucherToRouters;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelStay;
use App\Models\Hotel\HotelVoucher;
use App\Models\Hotel\HotelVoucherRouterSync;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HotelHotspotMaintain extends Command
{
    protected $signature =
        'hotel-hotspot:maintain';

    protected $description =
        'Expire checked-out vouchers and reconcile Hotel MikroTik voucher state.';

    public function handle(): int
    {
        $expired = 0;
        $expiryQueued = 0;
        $upsertQueued = 0;
        $cleanupQueued = 0;

        /*
         * Normal checkout/expiry lifecycle.
         */
        $ids =
            HotelVoucher::query()
                ->whereNotNull(
                    'expires_at'
                )
                ->where(
                    'expires_at',
                    '<=',
                    now()
                )
                ->whereNotIn(
                    'status',
                    [
                        'expired',
                        'revoked',
                    ]
                )
                ->orderBy('id')
                ->limit(500)
                ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(
                function () use (
                    $id,
                    &$expired,
                    &$expiryQueued
                ): void {
                    $voucher =
                        HotelVoucher::query()
                            ->lockForUpdate()
                            ->find($id);

                    if (!$voucher) {
                        return;
                    }

                    if (
                        !$voucher->expires_at
                        || $voucher
                            ->expires_at
                            ->gt(now())
                        || in_array(
                            $voucher->status,
                            [
                                'expired',
                                'revoked',
                            ],
                            true
                        )
                    ) {
                        return;
                    }

                    $voucher->forceFill([
                        'status' =>
                            'expired',
                    ])->save();

                    if (
                        $voucher
                            ->hotel_stay_id
                    ) {
                        HotelStay::query()
                            ->whereKey(
                                $voucher
                                    ->hotel_stay_id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->where(
                                'check_out_at',
                                '<=',
                                now()
                            )
                            ->update([
                                'status' =>
                                    'checked_out',
                            ]);
                    }

                    SyncHotelVoucherToRouters::dispatch(
                        $voucher->id,
                        'expire'
                    )->afterCommit();

                    $expired++;
                    $expiryQueued++;
                }
            );
        }

        $routers =
            HotelRouter::query()
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('id')
                ->get([
                    'id',
                    'hotel_id',
                ]);

        if ($routers->isNotEmpty()) {
            /*
             * HOTEL_RECONCILIATION_V1
             *
             * Covers:
             * - new Hotel router backfill
             * - failed sync retry
             * - stuck pending jobs
             * - six-hour drift correction
             */
            $activeVouchers =
                HotelVoucher::query()
                    ->whereIn(
                        'status',
                        [
                            'unused',
                            'active',
                        ]
                    )
                    ->where(
                        function (
                            $query
                        ): void {
                            $query
                                ->whereNull(
                                    'expires_at'
                                )
                                ->orWhere(
                                    'expires_at',
                                    '>',
                                    now()
                                );
                        }
                    )
                    ->orderBy('id')
                    ->limit(500)
                    ->get([
                        'id',
                        'hotel_id',
                    ]);

            foreach (
                $activeVouchers
                as $voucher
            ) {
                $needsSync =
                    false;

                foreach (
                    $routers->where(
                        'hotel_id',
                        $voucher->hotel_id
                    )
                    as $router
                ) {
                    $sync =
                        HotelVoucherRouterSync::query()
                            ->where(
                                'hotel_voucher_id',
                                $voucher->id
                            )
                            ->where(
                                'hotel_router_id',
                                $router->id
                            )
                            ->first();

                    /*
                     * New router: no row yet.
                     */
                    if (!$sync) {
                        $needsSync =
                            true;

                        break;
                    }

                    if (
                        $sync->status
                        === 'synced'
                    ) {
                        $last =
                            $sync
                                ->synced_at
                                ? CarbonImmutable::parse(
                                    $sync
                                        ->synced_at
                                )
                                : null;

                        if (
                            !$last
                            || $last->lte(
                                now()
                                    ->subHours(6)
                            )
                        ) {
                            $needsSync =
                                true;

                            break;
                        }

                        continue;
                    }

                    $attempt =
                        $sync
                            ->last_attempted_at
                            ? CarbonImmutable::parse(
                                $sync
                                    ->last_attempted_at
                            )
                            : null;

                    if (
                        $sync->status
                            === 'failed'
                        && $attempt
                        && $attempt->gt(
                            now()
                                ->subMinutes(5)
                        )
                    ) {
                        continue;
                    }

                    if (
                        $sync->status
                            === 'pending'
                        && $attempt
                        && $attempt->gt(
                            now()
                                ->subMinutes(10)
                        )
                    ) {
                        continue;
                    }

                    $needsSync =
                        true;

                    break;
                }

                if ($needsSync) {
                    SyncHotelVoucherToRouters::dispatch(
                        $voucher->id,
                        'upsert'
                    );

                    $upsertQueued++;
                }
            }

            /*
             * Re-run unfinished cleanup on
             * revoked/expired/deleted vouchers.
             */
            $cleanupVouchers =
                HotelVoucher::withTrashed()
                    ->where(
                        function (
                            $query
                        ): void {
                            $query
                                ->whereIn(
                                    'status',
                                    [
                                        'expired',
                                        'revoked',
                                    ]
                                )
                                ->orWhereNotNull(
                                    'deleted_at'
                                )
                                ->orWhere(
                                    function (
                                        $query
                                    ): void {
                                        $query
                                            ->whereNotNull(
                                                'expires_at'
                                            )
                                            ->where(
                                                'expires_at',
                                                '<=',
                                                now()
                                            );
                                    }
                                );
                        }
                    )
                    ->orderBy('id')
                    ->limit(500)
                    ->get([
                        'id',
                        'hotel_id',
                    ]);

            foreach (
                $cleanupVouchers
                as $voucher
            ) {
                $routerIds =
                    $routers
                        ->where(
                            'hotel_id',
                            $voucher->hotel_id
                        )
                        ->pluck('id');

                if (
                    $routerIds
                        ->isEmpty()
                ) {
                    continue;
                }

                $needsCleanup =
                    HotelVoucherRouterSync::query()
                        ->where(
                            'hotel_voucher_id',
                            $voucher->id
                        )
                        ->whereIn(
                            'hotel_router_id',
                            $routerIds
                        )
                        ->where(
                            'status',
                            '!=',
                            'expired'
                        )
                        ->where(
                            function (
                                $query
                            ): void {
                                $query
                                    ->whereNull(
                                        'last_attempted_at'
                                    )
                                    ->orWhere(
                                        'last_attempted_at',
                                        '<=',
                                        now()
                                            ->subMinutes(5)
                                    );
                            }
                        )
                        ->exists();

                if ($needsCleanup) {
                    SyncHotelVoucherToRouters::dispatch(
                        $voucher->id,
                        'expire'
                    );

                    $cleanupQueued++;
                }
            }
        }

        $this->line(
            'EXPIRED='
            . $expired
        );

        $this->line(
            'EXPIRY_QUEUED='
            . $expiryQueued
        );

        $this->line(
            'RECONCILE_UPSERT_QUEUED='
            . $upsertQueued
        );

        $this->line(
            'RECONCILE_EXPIRE_QUEUED='
            . $cleanupQueued
        );

        return self::SUCCESS;
    }
}
