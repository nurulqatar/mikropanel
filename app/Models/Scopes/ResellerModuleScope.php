<?php

namespace App\Models\Scopes;

use App\Services\Reseller\ResellerModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ResellerModuleScope implements Scope
{
    public function __construct(
        private readonly string $module
    ) {
    }

    public function apply(
        Builder $builder,
        Model $model
    ): void {
        $user =
            Auth::user();

        /*
         * Super Admin, background jobs and public
         * operations are not restricted here.
         */
        if (
            !$user
            || !$user->reseller_id
        ) {
            return;
        }

        $enabled =
            app(
                ResellerModuleService::class
            )->enabled(
                (int)
                $user->reseller_id,
                $this->module
            );

        if (!$enabled) {
            $builder->whereRaw(
                '1 = 0'
            );
        }
    }
}
