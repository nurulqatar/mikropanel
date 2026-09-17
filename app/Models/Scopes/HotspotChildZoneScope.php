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
         * Reseller Owner and Manager are all-zone.
         * Platform admins are handled by their normal
         * tenant access rules.
         */
        if (
            !$user->reseller_id
            || $user->isResellerOwner()
            || $user->isManager()
        ) {
            return;
        }

        if (!$user->isOperator()) {
            return;
        }

        $zoneId =
            (int) (
                $user->zone_id
                ?? 0
            );

        if (!$zoneId) {
            $builder->whereRaw('1 = 0');

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
}
