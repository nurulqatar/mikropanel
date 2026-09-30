<?php

namespace App\Providers;

use App\Models\Scopes\ResellerModuleScope;
use App\Services\Reseller\ResellerModuleService;
use Illuminate\Support\ServiceProvider;

class ResellerModuleScopeServiceProvider
    extends ServiceProvider
{
    public function boot(): void
    {
        $groups = [
            ResellerModuleService::MAC_CLIENT => [
                \App\Models\Client::class,
                \App\Models\ClientRefund::class,
                \App\Models\ClientMonthlyUsage::class,
                \App\Models\ClientRouterBinding::class,
                \App\Models\ClientCustomField::class,
                \App\Models\Invoice::class,
                \App\Models\Payment::class,
                \App\Models\Package::class,
                \App\Models\IpRange::class,
                \App\Models\ManagerCashLedgerEntry::class,
                \App\Models\ManagerCashHandover::class,
            ],

            ResellerModuleService::HOTSPOT => [
                \App\Models\HotspotServer::class,
                \App\Models\HotspotPlan::class,
                \App\Models\HotspotBatch::class,
                \App\Models\HotspotVoucher::class,
                \App\Models\HotspotInvoice::class,
                \App\Models\HotspotPayment::class,
                \App\Models\HotspotSession::class,
                \App\Models\HotspotBranding::class,
                \App\Models\HotspotSeller::class,
                \App\Models\HotspotSellerCollection::class,
            ],
        ];

        foreach (
            $groups
            as $module => $models
        ) {
            foreach (
                $models as $model
            ) {
                if (!class_exists($model)) {
                    continue;
                }

                $model::addGlobalScope(
                    new ResellerModuleScope(
                        $module
                    )
                );
            }
        }
    }
}
