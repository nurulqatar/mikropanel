<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ZoneScope implements Scope
{
    public function apply(
        Builder $builder,
        Model $model
    ): void {
        $user = Auth::user();

        /*
         * CLI / queue / scheduler.
         */
        if (!$user) {
            return;
        }

        /*
         * Platform-level accounts are not
         * restricted by reseller Network Zones.
         */
        if (!$user->reseller_id) {
            return;
        }

        $column =
            $model->qualifyColumn(
                'zone_id'
            );

        /*
         * Reseller Owner remains all-zone.
         *
         * Existing AccountingController may set
         * accounting_zone_id explicitly.
         */
        if (
            $user->role === 'reseller'
        ) {
            $accountingZoneId =
                $this->accountingZoneId();

            if ($accountingZoneId) {
                $builder->where(
                    $column,
                    $accountingZoneId
                );
            }

            return;
        }

        /*
         * Manager:
         *
         * zone_id on users remains NULL because a
         * Manager may move between sites.
         *
         * Every zone-aware operational query is
         * limited to the currently selected
         * Network Zone stored in session.
         *
         * Accounting's explicit zone filter wins
         * when AccountingController sets one.
         */
        if ($user->isManager()) {
            $zoneId =
                $this->accountingZoneId()
                ?: $this->sessionZoneId();

            if (!$zoneId) {
                $builder->whereRaw(
                    '1 = 0'
                );

                return;
            }

            $builder->where(
                $column,
                $zoneId
            );

            return;
        }

        /*
         * Normal Operator is permanently bound
         * to exactly one Network Zone.
         */
        $zoneId =
            (int) (
                $user->zone_id
                ?? 0
            );

        if ($zoneId <= 0) {
            $builder->whereRaw(
                '1 = 0'
            );

            return;
        }

        $builder->where(
            $column,
            $zoneId
        );
    }

    private function sessionZoneId(): ?int
    {
        if (
            !app()->bound(
                'request'
            )
        ) {
            return null;
        }

        $request = request();

        if (
            !$request->hasSession()
        ) {
            return null;
        }

        $value =
            $request
                ->session()
                ->get(
                    'network_zone_id'
                );

        return $value
            ? (int) $value
            : null;
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

        $request = request();

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
