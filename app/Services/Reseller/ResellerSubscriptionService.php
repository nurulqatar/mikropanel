<?php

namespace App\Services\Reseller;

use App\Models\Reseller;
use App\Models\ResellerPlan;
use App\Models\ResellerSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResellerSubscriptionService
{
    public function start(
        Reseller $reseller,
        ResellerPlan $plan,
        int $createdBy,
        ?int $validityDays = null
    ): ResellerSubscription {
        return DB::transaction(
            function () use (
                $reseller,
                $plan,
                $createdBy,
                $validityDays
            ): ResellerSubscription {
                $locked =
                    Reseller::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $reseller->id
                        );

                ResellerSubscription::query()
                    ->where(
                        'reseller_id',
                        $locked->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->update([
                        'status' =>
                            'replaced',
                    ]);

                $days = max(
                    1,
                    $validityDays
                    ?? (int)
                    $plan->validity_days
                );

                $now = Carbon::now(
                    $locked->timezone
                    ?: 'Asia/Qatar'
                );

                return ResellerSubscription::create([
                    'reseller_id' =>
                        $locked->id,

                    'reseller_plan_id' =>
                        $plan->id,

                    'status' =>
                        'active',

                    'client_limit' =>
                        $plan
                            ->client_limit,

                    'operator_limit' =>
                        $plan
                            ->operator_limit,

                    'router_limit' =>
                        $plan
                            ->router_limit,

                    'price' =>
                        $plan->price,

                    'starts_at' =>
                        $now,

                    'expires_at' =>
                        $now
                            ->copy()
                            ->addDays(
                                $days
                            ),

                    'created_by' =>
                        $createdBy,
                ]);
            }
        );
    }

    public function renew(
        Reseller $reseller,
        int $createdBy,
        ?ResellerPlan $plan = null,
        ?int $validityDays = null
    ): ResellerSubscription {
        return DB::transaction(
            function () use (
                $reseller,
                $createdBy,
                $plan,
                $validityDays
            ): ResellerSubscription {
                $locked =
                    Reseller::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $reseller->id
                        );

                $current =
                    ResellerSubscription::query()
                        ->where(
                            'reseller_id',
                            $locked->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->lockForUpdate()
                        ->latest('id')
                        ->first();

                if (!$plan) {
                    if (
                        !$current
                        || !$current
                            ->reseller_plan_id
                    ) {
                        throw ValidationException::withMessages([
                            'plan' =>
                                'No current reseller plan found.',
                        ]);
                    }

                    $plan =
                        ResellerPlan::query()
                            ->findOrFail(
                                $current
                                    ->reseller_plan_id
                            );
                }

                $days = max(
                    1,
                    $validityDays
                    ?? (int)
                    $plan->validity_days
                );

                $now = Carbon::now(
                    $locked->timezone
                    ?: 'Asia/Qatar'
                );

                $base =
                    $current
                    && $current->expires_at
                    && $current
                        ->expires_at
                        ->isFuture()
                        ? $current
                            ->expires_at
                            ->copy()
                        : $now->copy();

                if ($current) {
                    $current->forceFill([
                        'status' =>
                            'renewed',
                    ])->save();
                }

                return ResellerSubscription::create([
                    'reseller_id' =>
                        $locked->id,

                    'reseller_plan_id' =>
                        $plan->id,

                    'status' =>
                        'active',

                    'client_limit' =>
                        $plan
                            ->client_limit,

                    'operator_limit' =>
                        $plan
                            ->operator_limit,

                    'router_limit' =>
                        $plan
                            ->router_limit,

                    'price' =>
                        $plan->price,

                    'starts_at' =>
                        $now,

                    'expires_at' =>
                        $base
                            ->addDays(
                                $days
                            ),

                    'created_by' =>
                        $createdBy,
                ]);
            }
        );
    }
}
