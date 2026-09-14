<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ResellerScope implements Scope
{
    public function apply(
        Builder $builder,
        Model $model
    ): void {
        $user = Auth::user();

        /*
         * CLI / scheduler / queue has no web user,
         * so background synchronization can process
         * every tenant explicitly.
         */
        if (!$user) {
            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $column =
            $model->qualifyColumn(
                'reseller_id'
            );

        if ($user->reseller_id) {
            $builder->where(
                $column,
                (int)
                $user->reseller_id
            );

            return;
        }

        /*
         * Existing platform operators only see
         * existing platform-owned records.
         */
        $builder->whereNull(
            $column
        );
    }
}
