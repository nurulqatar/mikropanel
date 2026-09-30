<?php

namespace App\Jobs;

use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotZoneVoucherService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionHotspotVoucher implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;
    public int $backoff = 5;
    public int $uniqueFor = 3600;

    public function __construct(
        public int $voucherId
    ) {
        $this->onQueue('router-sync');
    }

    public function uniqueId(): string
    {
        return (string) $this->voucherId;
    }

    public function handle(
        HotspotZoneVoucherService $service
    ): void {
        $voucher = HotspotVoucher::query()
            ->find($this->voucherId);

        if (
            !$voucher
            || !in_array(
                $voucher->status,
                ['unused', 'active'],
                true
            )
        ) {
            return;
        }

        $service->provisionVoucher($voucher);
    }

    public function failed(
        Throwable $exception
    ): void {
        Log::error(
            'Hotspot zone voucher provisioning failed.',
            [
                'voucher_id' => $this->voucherId,
                'message' => $exception->getMessage(),
            ]
        );
    }
}
