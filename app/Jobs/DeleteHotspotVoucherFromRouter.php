<?php

namespace App\Jobs;

use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteHotspotVoucherFromRouter implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;
    public int $backoff = 15;
    public int $uniqueFor = 300;

    public function __construct(
        public int $voucherId
    ) {
        $this->onQueue(
            'router-sync'
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->voucherId;
    }

    public function handle(
        HotspotRouterService $service
    ): void {
        $voucher =
            HotspotVoucher::withoutGlobalScopes()
                ->withTrashed()
                ->find(
                    $this->voucherId
                );

        if (
            !$voucher
            || !$voucher->mikrotik_user_id
        ) {
            return;
        }

        $service->deleteVoucherFromRouter(
            $voucher
        );

        $voucher->forceFill([
            'mikrotik_user_id' => null,
        ])->saveQuietly();
    }

    public function failed(
        Throwable $exception
    ): void {
        Log::error(
            'Hotspot RouterOS voucher removal failed.',
            [
                'voucher_id' =>
                    $this->voucherId,

                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
}
