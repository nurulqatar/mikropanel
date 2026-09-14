<?php

namespace App\Services\Reseller;

use App\Models\Client;
use App\Models\ClientMonthlyUsage;
use App\Models\ClientRouterBinding;
use App\Models\HotspotBatch;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotPlan;
use App\Models\HotspotServer;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use Illuminate\Database\Eloquent\Model;

class TenantOwnershipService
{
    public function infer(
        Model $model
    ): ?int {
        return match (
            $model::class
        ) {
            Invoice::class =>
                $this->fromParent(
                    Client::class,
                    $model->getAttribute(
                        'client_id'
                    )
                ),

            Payment::class =>
                $this->first([
                    $this->fromParent(
                        Invoice::class,
                        $model->getAttribute(
                            'invoice_id'
                        )
                    ),

                    $this->fromParent(
                        Client::class,
                        $model->getAttribute(
                            'client_id'
                        )
                    ),
                ]),

            ClientMonthlyUsage::class =>
                $this->fromParent(
                    Client::class,
                    $model->getAttribute(
                        'client_id'
                    )
                ),

            ClientRouterBinding::class =>
                $this->first([
                    $this->fromParent(
                        Client::class,
                        $model->getAttribute(
                            'client_id'
                        )
                    ),

                    $this->fromParent(
                        Router::class,
                        $model->getAttribute(
                            'router_id'
                        )
                    ),
                ]),

            HotspotServer::class =>
                $this->fromParent(
                    Router::class,
                    $model->getAttribute(
                        'router_id'
                    )
                ),

            HotspotPlan::class =>
                $this->fromParent(
                    HotspotServer::class,
                    $model->getAttribute(
                        'hotspot_server_id'
                    )
                ),

            HotspotBatch::class =>
                $this->first([
                    $this->fromParent(
                        HotspotServer::class,
                        $model->getAttribute(
                            'hotspot_server_id'
                        )
                    ),

                    $this->fromParent(
                        HotspotPlan::class,
                        $model->getAttribute(
                            'hotspot_plan_id'
                        )
                    ),
                ]),

            HotspotVoucher::class =>
                $this->first([
                    $this->fromParent(
                        HotspotBatch::class,
                        $model->getAttribute(
                            'hotspot_batch_id'
                        )
                    ),

                    $this->fromParent(
                        HotspotServer::class,
                        $model->getAttribute(
                            'hotspot_server_id'
                        )
                    ),

                    $this->fromParent(
                        HotspotPlan::class,
                        $model->getAttribute(
                            'hotspot_plan_id'
                        )
                    ),
                ]),

            HotspotInvoice::class =>
                $this->fromParent(
                    HotspotVoucher::class,
                    $model->getAttribute(
                        'hotspot_voucher_id'
                    )
                ),

            HotspotPayment::class =>
                $this->first([
                    $this->fromParent(
                        HotspotInvoice::class,
                        $model->getAttribute(
                            'hotspot_invoice_id'
                        )
                    ),

                    $this->fromParent(
                        HotspotVoucher::class,
                        $model->getAttribute(
                            'hotspot_voucher_id'
                        )
                    ),
                ]),

            HotspotSession::class =>
                $this->first([
                    $this->fromParent(
                        HotspotVoucher::class,
                        $model->getAttribute(
                            'hotspot_voucher_id'
                        )
                    ),

                    $this->fromParent(
                        HotspotServer::class,
                        $model->getAttribute(
                            'hotspot_server_id'
                        )
                    ),
                ]),

            default => null,
        };
    }

    private function fromParent(
        string $modelClass,
        mixed $id
    ): ?int {
        if (!$id || !class_exists($modelClass)) {
            return null;
        }

        $value =
            $modelClass::withoutGlobalScopes()
                ->whereKey($id)
                ->value(
                    'reseller_id'
                );

        return $value === null
            ? null
            : (int) $value;
    }

    private function first(
        array $values
    ): ?int {
        foreach ($values as $value) {
            if ($value !== null) {
                return (int) $value;
            }
        }

        return null;
    }
}
