<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\Reseller\ResellerNotificationService;
use App\Services\Reseller\ResellerUsageService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MonitorResellerSubscriptions extends Command
{
    protected $signature =
        'resellers:monitor-subscriptions';

    protected $description =
        'Create reseller subscription and usage alerts';

    public function handle(
        ResellerUsageService $usage,
        ResellerNotificationService $notifications
    ): int {
        $checked = 0;
        $createdBefore = 0;
        $createdAfter = 0;

        $notificationClass =
            \App\Models\ResellerNotification::class;

        $createdBefore =
            $notificationClass::query()
                ->withoutGlobalScopes()
                ->count();

        Reseller::query()
            ->orderBy('id')
            ->each(
                function (
                    Reseller $reseller
                ) use (
                    $usage,
                    $notifications,
                    &$checked
                ): void {
                    $checked++;

                    $subscription =
                        $usage->subscription(
                            $reseller
                        );

                    if ($subscription) {
                        $this->subscriptionAlerts(
                            $reseller,
                            $subscription,
                            $notifications
                        );
                    }

                    $this->usageAlerts(
                        $reseller,
                        $usage,
                        $notifications
                    );
                }
            );

        $createdAfter =
            $notificationClass::query()
                ->withoutGlobalScopes()
                ->count();

        $this->info(
            'RESELLERS_CHECKED='
            . $checked
        );

        $this->info(
            'NEW_NOTIFICATIONS='
            . max(
                0,
                $createdAfter
                - $createdBefore
            )
        );

        return self::SUCCESS;
    }

    private function subscriptionAlerts(
        Reseller $reseller,
        $subscription,
        ResellerNotificationService $notifications
    ): void {
        if (!$subscription->expires_at) {
            return;
        }

        $timezone =
            $reseller->timezone
            ?: 'Asia/Qatar';

        $now =
            Carbon::now(
                $timezone
            );

        $expiry =
            $subscription
                ->expires_at
                ->copy()
                ->timezone(
                    $timezone
                );

        if ($expiry->lt($now)) {
            $notifications->unique(
                $reseller,
                'subscription_expired',
                'danger',
                'Subscription Expired',
                'Your reseller subscription has expired.',
                'subscription:'
                    . $subscription->id
                    . ':expired',
                [
                    'expires_at' =>
                        $expiry
                            ->toDateTimeString(),
                ]
            );

            return;
        }

        $seconds =
            $now->diffInSeconds(
                $expiry,
                false
            );

        $days =
            (int)
            ceil(
                $seconds / 86400
            );

        if ($days <= 1) {
            $stage = '1d';
            $level = 'danger';
            $title =
                'Subscription Expires Tomorrow';
        } elseif ($days <= 3) {
            $stage = '3d';
            $level = 'warning';
            $title =
                'Subscription Expires in 3 Days';
        } elseif ($days <= 7) {
            $stage = '7d';
            $level = 'warning';
            $title =
                'Subscription Expires Soon';
        } else {
            return;
        }

        $notifications->unique(
            $reseller,
            'subscription_expiring',
            $level,
            $title,
            'Your reseller subscription expires on '
                . $expiry->format(
                    'Y-m-d H:i'
                )
                . '.',
            'subscription:'
                . $subscription->id
                . ':'
                . $stage,
            [
                'expires_at' =>
                    $expiry
                        ->toDateTimeString(),

                'days_remaining' =>
                    $days,
            ]
        );
    }

    private function usageAlerts(
        Reseller $reseller,
        ResellerUsageService $usage,
        ResellerNotificationService $notifications
    ): void {
        $limit =
            $usage->clientLimit(
                $reseller
            );

        if ($limit <= 0) {
            return;
        }

        $used =
            $usage->usedClientSlots(
                $reseller
            );

        $percent =
            (int)
            floor(
                ($used / $limit)
                * 100
            );

        if ($percent >= 100) {
            $stage = '100';
            $level = 'danger';
            $title =
                'Client Limit Reached';
        } elseif ($percent >= 90) {
            $stage = '90';
            $level = 'warning';
            $title =
                'Client Capacity 90% Used';
        } elseif ($percent >= 80) {
            $stage = '80';
            $level = 'warning';
            $title =
                'Client Capacity 80% Used';
        } else {
            return;
        }

        $notifications->unique(
            $reseller,
            'client_limit',
            $level,
            $title,
            "{$used} of {$limit} client slots are currently used.",
            'client-limit:'
                . $reseller->id
                . ':'
                . now(
                    $reseller->timezone
                    ?: 'Asia/Qatar'
                )->format('Ym')
                . ':'
                . $stage,
            [
                'used' =>
                    $used,

                'limit' =>
                    $limit,

                'percent' =>
                    $percent,
            ]
        );
    }
}
