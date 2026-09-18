<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\Reseller\ResellerPlanBillingService;
use Illuminate\Console\Command;
use Throwable;

class AutoRenewResellerSubscriptions extends Command
{
    protected $signature =
        'resellers:auto-renew';

    protected $description =
        'Automatically renew expired paid reseller subscriptions from wallet balance';

    public function handle(
        ResellerPlanBillingService $billing
    ): int {
        $renewed = 0;
        $skipped = 0;
        $failed = 0;

        Reseller::query()
            ->where(
                'status',
                'active'
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function (
                    $resellers
                ) use (
                    $billing,
                    &$renewed,
                    &$skipped,
                    &$failed
                ): void {
                    foreach (
                        $resellers
                        as $reseller
                    ) {
                        try {
                            if (
                                $billing->autoRenew(
                                    $reseller
                                )
                            ) {
                                $renewed++;
                            } else {
                                $skipped++;
                            }
                        } catch (Throwable $e) {
                            $failed++;

                            report($e);

                            $this->error(
                                'Reseller #'
                                . $reseller->id
                                . ': '
                                . $e->getMessage()
                            );
                        }
                    }
                }
            );

        $this->info(
            'RENEWED='
            . $renewed
            . ' SKIPPED='
            . $skipped
            . ' FAILED='
            . $failed
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
