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
        $user =
            Auth::user();

        /*
         * Queue / scheduler / CLI.
         */
        if (!$user) {
            return;
        }

        /*
         * Platform admin.
         */
        if (!$user->reseller_id) {
            return;
        }

        /*
         * Reseller Owner / Manager:
         * ResellerScope still keeps them inside
         * their own company.
         */
        if (
            $user->role === 'reseller'
            || $user->isManager()
        ) {
            return;
        }

        /*
         * Operator:
         * only assigned zone.
         */
        if ($user->zone_id) {
            $builder->where(
                $model->qualifyColumn(
                    'zone_id'
                ),
                (int)
                $user->zone_id
            );

            return;
        }

        /*
         * Never expose all zones accidentally.
         */
        $builder->whereRaw(
            '1 = 0'
        );
    }
}
