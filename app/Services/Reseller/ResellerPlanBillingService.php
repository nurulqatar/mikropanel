<?php

namespace App\Services\Reseller;

use App\Models\Reseller;
use App\Models\ResellerPlan;
use App\Models\ResellerSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResellerPlanBillingService
{
    public function __construct(
        private ResellerWalletService $wallet,
        private ResellerSubscriptionService $subscriptions,
        private ResellerNotificationService $notifications
    ) {
    }

    public function upgrade(
        Reseller $reseller,
        ResellerPlan $plan,
        int $actorId
    ): ResellerSubscription {
        return DB::transaction(
            function () use (
                $reseller,
                $plan,
                $actorId
            ): ResellerSubscription {
                $locked =
                    Reseller::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $reseller->id
                        );

                if ($locked->status !== 'active') {
                    throw ValidationException::withMessages([
                        'plan' =>
                            'Reseller account is not active.',
                    ]);
                }

                $target =
                    ResellerPlan::query()
                        ->whereKey(
                            $plan->id
                        )
                        ->where(
                            'active',
                            true
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$target) {
                    throw ValidationException::withMessages([
                        'plan' =>
                            'Selected reseller package is unavailable.',
                    ]);
                }

                $price =
                    round(
                        (float)
                        $target->price,
                        2
                    );

                if ($price <= 0) {
                    throw ValidationException::withMessages([
                        'plan' =>
                            'Free trial packages cannot be purchased or renewed from wallet.',
                    ]);
                }

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

                $currentLimit =
                    (int) (
                        $current
                            ?->client_limit
                        ?? 0
                    );

                if (
                    (int)
                    $target->client_limit
                    <= $currentLimit
                ) {
                    throw ValidationException::withMessages([
                        'plan' =>
                            'Select a package with a higher client limit.',
                    ]);
                }

                $balance =
                    round(
                        (float)
                        $locked->wallet_balance,
                        2
                    );

                if (
                    $balance + 0.0001
                    < $price
                ) {
                    throw ValidationException::withMessages([
                        'plan' =>
                            'Insufficient wallet balance. Required QAR '
                            . number_format(
                                $price,
                                2
                            )
                            . ', available QAR '
                            . number_format(
                                $balance,
                                2
                            )
                            . '.',
                    ]);
                }

                $reference =
                    'upgrade:'
                    . (
                        $current?->id
                        ?? 'none'
                    )
                    . ':'
                    . $target->id;

                $this->wallet->debit(
                    $locked,
                    $price,
                    'subscription_upgrade',
                    $actorId,
                    'Wallet',
                    $reference,
                    'Reseller self-upgrade to '
                        . $target->name
                );

                $subscription =
                    $this->subscriptions->start(
                        $locked,
                        $target,
                        $actorId
                    );

                $this->notifications->unique(
                    $locked,
                    'subscription_upgraded',
                    'success',
                    'Package Upgraded',
                    'Your reseller package was upgraded to '
                        . $target->name
                        . '. QAR '
                        . number_format(
                            $price,
                            2
                        )
                        . ' was deducted from your wallet.',
                    'subscription:upgrade:'
                        . $subscription->id,
                    [
                        'subscription_id' =>
                            $subscription->id,
                        'plan_id' =>
                            $target->id,
                        'price' =>
                            $price,
                    ]
                );

                return $subscription;
            },
            3
        );
    }

    public function autoRenew(
        Reseller $reseller
    ): bool {
        return DB::transaction(
            function () use (
                $reseller
            ): bool {
                $locked =
                    Reseller::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $reseller->id
                        );

                if ($locked->status !== 'active') {
                    return false;
                }

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

                if (
                    !$current
                    || !$current->expires_at
                ) {
                    return false;
                }

                $now =
                    now(
                        $locked->timezone
                        ?: 'Asia/Qatar'
                    );

                if (
                    $current
                        ->expires_at
                        ->gt(
                            $now
                        )
                ) {
                    return false;
                }

                if (
                    !$current
                        ->reseller_plan_id
                ) {
                    return false;
                }

                $plan =
                    ResellerPlan::query()
                        ->whereKey(
                            $current
                                ->reseller_plan_id
                        )
                        ->where(
                            'active',
                            true
                        )
                        ->first();

                if (!$plan) {
                    return false;
                }

                $price =
                    round(
                        (float)
                        $plan->price,
                        2
                    );

                /*
                 * Free trial must never renew forever.
                 */
                if ($price <= 0) {
                    return false;
                }

                $balance =
                    round(
                        (float)
                        $locked->wallet_balance,
                        2
                    );

                if (
                    $balance + 0.0001
                    < $price
                ) {
                    $this->notifications->unique(
                        $locked,
                        'subscription_auto_renew_failed',
                        'warning',
                        'Auto Renew Failed',
                        'Your '
                            . $plan->name
                            . ' package expired, but your wallet balance is insufficient. Required QAR '
                            . number_format(
                                $price,
                                2
                            )
                            . ', available QAR '
                            . number_format(
                                $balance,
                                2
                            )
                            . '.',
                        'subscription:auto-renew-insufficient:'
                            . $current->id,
                        [
                            'subscription_id' =>
                                $current->id,
                            'plan_id' =>
                                $plan->id,
                            'required' =>
                                $price,
                            'balance' =>
                                $balance,
                        ]
                    );

                    return false;
                }

                $actorId =
                    (int) (
                        $locked->owner_user_id
                        ?: $current->created_by
                        ?: 0
                    );

                if ($actorId < 1) {
                    $this->notifications->unique(
                        $locked,
                        'subscription_auto_renew_failed',
                        'warning',
                        'Auto Renew Failed',
                        'Package renewal could not determine the reseller owner account.',
                        'subscription:auto-renew-owner:'
                            . $current->id,
                        [
                            'subscription_id' =>
                                $current->id,
                        ]
                    );

                    return false;
                }

                $this->wallet->debit(
                    $locked,
                    $price,
                    'subscription_auto_renew',
                    $actorId,
                    'Wallet',
                    'renew:'
                        . $current->id,
                    'Automatic renewal of '
                        . $plan->name
                );

                $subscription =
                    $this->subscriptions->renew(
                        $locked,
                        $actorId,
                        $plan
                    );

                $this->notifications->unique(
                    $locked,
                    'subscription_auto_renewed',
                    'success',
                    'Package Auto Renewed',
                    'Your '
                        . $plan->name
                        . ' package was automatically renewed. QAR '
                        . number_format(
                            $price,
                            2
                        )
                        . ' was deducted from your wallet.',
                    'subscription:auto-renewed:'
                        . $subscription->id,
                    [
                        'old_subscription_id' =>
                            $current->id,
                        'subscription_id' =>
                            $subscription->id,
                        'plan_id' =>
                            $plan->id,
                        'price' =>
                            $price,
                    ]
                );

                return true;
            },
            3
        );
    }
}
