<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientRefund;
use App\Models\ClientMonthlyUsage;
use App\Models\ClientRouterBinding;
use App\Models\Expense;
use App\Models\HotspotBatch;
use App\Models\HotspotBranding;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotPlan;
use App\Models\HotspotServer;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use App\Models\Invoice;
use App\Models\NetworkZone;
use App\Models\IpRange;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerNotification;
use App\Models\Router;
use App\Models\Scopes\ResellerScope;
use App\Services\Reseller\ResellerUsageService;
use App\Services\Reseller\TenantOwnershipService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class ResellerTenancyServiceProvider extends ServiceProvider
{
    public function boot(
        TenantOwnershipService $ownership,
        ResellerUsageService $usage
    ): void {
        foreach (
            $this->tenantModels()
            as $modelClass
        ) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();

            if (
                !Schema::hasTable(
                    $model->getTable()
                )
                || !Schema::hasColumn(
                    $model->getTable(),
                    'reseller_id'
                )
            ) {
                continue;
            }

            $modelClass::addGlobalScope(
                new ResellerScope()
            );

            $modelClass::creating(
                function (
                    Model $model
                ) use (
                    $ownership,
                    $usage
                ): void {
                    $this->applyOwnership(
                        $model,
                        $ownership
                    );

                    $this->applyLimits(
                        $model,
                        $usage
                    );
                }
            );

            $modelClass::saving(
                function (
                    Model $model
                ): void {
                    $user =
                        Auth::user();

                    if (
                        !$user
                        || !$user
                            ->reseller_id
                        || !$model
                            ->exists
                    ) {
                        return;
                    }

                    $original =
                        $model
                            ->getRawOriginal(
                                'reseller_id'
                            );

                    $current =
                        $model
                            ->getAttribute(
                                'reseller_id'
                            );

                    if (
                        (int) $original
                        !== (int)
                        $user->reseller_id
                        || (int) $current
                        !== (int)
                        $user->reseller_id
                    ) {
                        throw ValidationException::withMessages([
                            'tenant' =>
                                'Cross-reseller modification is not allowed.',
                        ]);
                    }
                }
            );
        }
    }

    private function applyOwnership(
        Model $model,
        TenantOwnershipService $ownership
    ): void {
        $user =
            Auth::user();

        $userTenant =
            $user?->reseller_id
                ? (int)
                    $user->reseller_id
                : null;

        $explicit =
            $model->getAttribute(
                'reseller_id'
            );

        $explicit =
            $explicit === null
                ? null
                : (int) $explicit;

        $inferred =
            $ownership->infer(
                $model
            );

        if (
            $userTenant !== null
            && $explicit !== null
            && $explicit !== $userTenant
        ) {
            throw ValidationException::withMessages([
                'tenant' =>
                    'Cross-reseller creation is not allowed.',
            ]);
        }

        if (
            $userTenant !== null
            && $inferred !== null
            && $inferred !== $userTenant
        ) {
            throw ValidationException::withMessages([
                'tenant' =>
                    'The selected parent record belongs to another reseller.',
            ]);
        }

        $tenant =
            $userTenant
            ?? $explicit
            ?? $inferred;

        if ($tenant !== null) {
            $model->setAttribute(
                'reseller_id',
                $tenant
            );
        }
    }

    private function applyLimits(
        Model $model,
        ResellerUsageService $usage
    ): void {
        $user =
            Auth::user();

        if (
            !$user
            || !$user->reseller_id
        ) {
            return;
        }

        $reseller =
            Reseller::query()
                ->find(
                    $user->reseller_id
                );

        if (!$reseller) {
            throw ValidationException::withMessages([
                'tenant' =>
                    'Reseller account not found.',
            ]);
        }

        if ($model instanceof Client) {
            if (
                !$usage->canCreateClient(
                    $reseller
                )
            ) {
                throw ValidationException::withMessages([
                    'client' =>
                        'Client limit reached or reseller subscription is unavailable.',
                ]);
            }
        }

        if ($model instanceof Router) {
            if (
                !$usage
                    ->subscriptionIsUsable(
                        $reseller
                    )
                || $reseller->status
                    !== 'active'
                || $usage
                    ->remainingRouterSlots(
                        $reseller
                    ) <= 0
            ) {
                throw ValidationException::withMessages([
                    'router' =>
                        'Router limit reached or reseller subscription is unavailable.',
                ]);
            }
        }
    }

    private function tenantModels(): array
    {
        $models = [
            Client::class,
            NetworkZone::class,
            ClientRefund::class,
            ResellerNotification::class,
            Router::class,
            Package::class,
            IpRange::class,
            Invoice::class,
            Payment::class,
            Expense::class,
            ActivityLog::class,
            ClientMonthlyUsage::class,
            ClientRouterBinding::class,
            HotspotServer::class,
            HotspotPlan::class,
            HotspotBatch::class,
            HotspotVoucher::class,
            HotspotInvoice::class,
            HotspotPayment::class,
            HotspotSession::class,
        ];

        if (
            class_exists(
                HotspotBranding::class
            )
        ) {
            $models[] =
                HotspotBranding::class;
        }

        if (
            class_exists(
                \App\Models\ClientCustomField::class
            )
        ) {
            $models[] =
                \App\Models\ClientCustomField::class;
        }

        return $models;
    }
}
