<?php

namespace App\Console\Commands;

use App\Jobs\SuspendHotspotVoucher;
use App\Models\HotspotVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireHotspotVouchers extends Command
{
    protected $signature =
        'hotspot:expire';

    protected $description =
        'Expire Hotspot vouchers whose validity has ended';

    public function handle(): int
    {
        $expired = 0;

        HotspotVoucher::query()
            ->where(
                'status',
                'active'
            )
            ->whereNotNull(
                'expires_at'
            )
            ->where(
                'expires_at',
                '<=',
                now()
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function (
                    $vouchers
                ) use (&$expired): void {
                    foreach (
                        $vouchers
                        as $voucher
                    ) {
                        $changed =
                            DB::transaction(
                                function () use (
                                    $voucher
                                ): bool {
                                    $locked =
                                        HotspotVoucher::query()
                                            ->lockForUpdate()
                                            ->find(
                                                $voucher
                                                    ->id
                                            );

                                    if (
                                        !$locked
                                        || $locked
                                            ->status
                                            !== 'active'
                                        || !$locked
                                            ->expires_at
                                        || $locked
                                            ->expires_at
                                            ->isFuture()
                                    ) {
                                        return false;
                                    }

                                    $locked
                                        ->forceFill([
                                            'status' =>
                                                'expired',
                                        ])
                                        ->save();

                                    return true;
                                }
                            );

                        if (!$changed) {
                            continue;
                        }

                        SuspendHotspotVoucher::dispatch(
                            $voucher->id
                        );

                        $expired++;
                    }
                }
            );

        $this->info(
            "HOTSPOT_EXPIRED={$expired}"
        );

        return self::SUCCESS;
    }
}
