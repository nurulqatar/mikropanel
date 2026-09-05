<?php

namespace App\Jobs;

use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotRouterService;
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
    public int $uniqueFor = 120;

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
            HotspotVoucher::query()
                ->find(
                    $this->voucherId
                );

        if (
            !$voucher
            || !$voucher->sold_at
            || !in_array(
                $voucher->status,
                [
                    'unused',
                    'active',
                ],
                true
            )
        ) {
            return;
        }

        $id =
            $service->provisionVoucher(
                $voucher
            );

        $voucher->forceFill([
            'mikrotik_user_id' =>
                $id,
        ])->save();
    }

    public function failed(
        Throwable $exception
    ): void {
        Log::error(
            'Hotspot voucher provisioning failed.',
            [
                'voucher_id' =>
                    $this->voucherId,

                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
}
