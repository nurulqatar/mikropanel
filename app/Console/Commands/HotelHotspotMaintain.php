<?php

namespace App\Console\Commands;

use App\Jobs\Hotel\SyncHotelVoucherToRouters;
use App\Models\Hotel\HotelStay;
use App\Models\Hotel\HotelVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HotelHotspotMaintain extends Command
{
    protected $signature =
        'hotel-hotspot:maintain';

    protected $description =
        'Expire checked-out hotel vouchers and queue MikroTik cleanup.';

    public function handle(): int
    {
        $expired = 0;
        $queued = 0;

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
                ->where(
                    'status',
                    '!=',
                    'expired'
                )
                ->orderBy('id')
                ->limit(500)
                ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(
                function () use (
                    $id,
                    &$expired,
                    &$queued
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
                        || $voucher
                            ->status
                            === 'expired'
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
                    $queued++;
                }
            );
        }

        $this->line(
            'EXPIRED=' . $expired
        );

        $this->line(
            'QUEUED=' . $queued
        );

        return self::SUCCESS;
    }
}
