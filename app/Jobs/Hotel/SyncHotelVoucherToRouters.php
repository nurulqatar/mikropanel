<?php

namespace App\Jobs\Hotel;

use App\Models\Hotel\HotelVoucher;
use App\Services\Hotel\HotelVoucherSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SyncHotelVoucherToRouters implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        public int $voucherId,
        public string $operation = 'auto'
    ) {
        $this->onQueue(
            'hotel-hotspot'
        );
    }

    public function backoff(): array
    {
        return [
            30,
            60,
            120,
            300,
        ];
    }

    public function handle(
        HotelVoucherSyncService $service
    ): void {
        $voucher =
            HotelVoucher::withTrashed()
                ->find(
                    $this->voucherId
                );

        if (!$voucher) {
            return;
        }

        $operation =
            in_array(
                $this->operation,
                [
                    'auto',
                    'upsert',
                    'expire',
                ],
                true
            )
                ? $this->operation
                : 'auto';

        $summary =
            $service->syncVoucher(
                $voucher,
                $operation
            );

        if (
            (int) (
                $summary['failed']
                ?? 0
            ) > 0
        ) {
            throw new RuntimeException(
                'Hotel voucher router sync incomplete: '
                . (int)
                    $summary['failed']
                . ' router(s) failed.'
            );
        }
    }
}
