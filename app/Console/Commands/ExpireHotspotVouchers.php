<?php

namespace App\Console\Commands;

use App\Jobs\DeleteHotspotVoucherFromRouter;
use App\Models\HotspotVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireHotspotVouchers extends Command
{
    protected $signature =
        'hotspot:expire';

    protected $description =
        'Expire Hotspot vouchers and remove RouterOS users';

    public function handle(): int
    {
        $expired = 0;
        $cleanup = 0;

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
                                                $voucher->id
                                            );

                                    if (
                                        !$locked
                                        || $locked->status
                                            !== 'active'
                                        || !$locked
                                            ->expires_at
                                        || $locked
                                            ->expires_at
                                            ->isFuture()
                                    ) {
                                        return false;
                                    }

                                    $locked->forceFill([
                                        'status' =>
                                            'expired',
                                    ])->save();

                                    /*
                                     * Remove from live panel.
                                     * Soft-delete preserves
                                     * historical reporting.
                                     */
                                    $locked->delete();

                                    return true;
                                }
                            );

                        if ($changed) {
                            $expired++;
                        }
                    }
                }
            );

        /*
         * Retry RouterOS deletion every
         * maintenance cycle until confirmed.
         */
        HotspotVoucher::withoutGlobalScopes()
            ->withTrashed()
            ->whereIn(
                'status',
                [
                    'expired',
                    'archived',
                ]
            )
            ->whereNotNull(
                'mikrotik_user_id'
            )
            ->orderBy('id')
            ->limit(500)
            ->each(
                function (
                    HotspotVoucher $voucher
                ) use (&$cleanup): void {
                    DeleteHotspotVoucherFromRouter::dispatch(
                        $voucher->id
                    );

                    $cleanup++;
                }
            );

        $this->info(
            "HOTSPOT_EXPIRED={$expired}"
        );

        $this->info(
            "HOTSPOT_ROUTER_DELETE_QUEUED={$cleanup}"
        );

        return self::SUCCESS;
    }
}
