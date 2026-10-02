<?php

namespace App\Models\Scopes;

use App\Models\HotspotBatch;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class HotspotChildZoneScope implements Scope
{
    public function apply(
        Builder $builder,
        Model $model
    ): void {
        $user = Auth::user();

        /*
         * CLI / queues run without an authenticated
         * web operator and must process explicit IDs.
         */
        if (!$user) {
            return;
        }

        /*
         * HOTSPOT_ACCOUNTING_ZONE_SCOPE_V5
         *
         * Owner / Manager:
         *   Operational Hotspot pages remain all-zone.
         *
         * Accounting:
         *   no explicit zone = all reseller zones.
         *   explicit zone = only Hotspot data belonging
         *   to that selected Network Zone.
         *
         * Normal Operator remains bound to user.zone_id.
         */
        if (!$user->reseller_id) {
            return;
        }

        $zoneId = null;

        if (
            $user->isResellerOwner()
            || $user->isManager()
        ) {
            $zoneId =
                $this->accountingZoneId();

            if (!$zoneId) {
                return;
            }
        } elseif ($user->isOperator()) {
            $zoneId =
                (int) (
                    $user->zone_id
                    ?? 0
                );

            if (!$zoneId) {
                $builder->whereRaw(
                    '1 = 0'
                );

                return;
            }
        } else {
            return;
        }

        if (
            $model instanceof HotspotVoucher
            || $model instanceof HotspotSession
            || $model instanceof HotspotBatch
        ) {
            $builder->whereHas(
                'server',
                function ($query) use (
                    $zoneId
                ): void {
                    $query->where(
                        'hotspot_servers.zone_id',
                        $zoneId
                    );
                }
            );

            return;
        }

        if (
            $model instanceof HotspotInvoice
            || $model instanceof HotspotPayment
        ) {
            $builder->whereHas(
                'voucher.server',
                function ($query) use (
                    $zoneId
                ): void {
                    $query->where(
                        'hotspot_servers.zone_id',
                        $zoneId
                    );
                }
            );
        }
    }
    private function accountingZoneId(): ?int
    {
        if (
            !app()->bound(
                'request'
            )
        ) {
            return null;
        }

        $request =
            request();

        $routeName =
            (string) (
                $request
                    ->route()
                    ?->getName()
                ?? ''
            );

        if (
            !str_starts_with(
                $routeName,
                'accounting.'
            )
        ) {
            return null;
        }

        $value =
            $request
                ->attributes
                ->get(
                    'accounting_zone_id'
                );

        return $value
            ? (int) $value
            : null;
    }

}
