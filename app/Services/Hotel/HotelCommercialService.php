<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelAuditLog;
use App\Models\Hotel\HotelBillingInvoice;
use App\Models\Hotel\HotelNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HotelCommercialService
{
    public function invoiceForSubscription(
        object $subscription
    ): HotelBillingInvoice {
        $existing =
            HotelBillingInvoice::query()
                ->where(
                    'hotel_subscription_id',
                    $subscription->id
                )
                ->first();

        if ($existing) {
            return $existing;
        }

        $hotel =
            Hotel::query()
                ->findOrFail(
                    $subscription->hotel_id
                );

        $amount =
            $this->subscriptionAmount(
                $subscription
            );

        $start =
            !empty(
                $subscription->starts_at
            )
                ? CarbonImmutable::parse(
                    $subscription->starts_at
                )
                : now()->toImmutable();

        $end =
            !empty(
                $subscription->expires_at
            )
                ? CarbonImmutable::parse(
                    $subscription->expires_at
                )
                : $start->addDays(30);

        $due =
            $start
                ->addDays(7);

        if ($due->gt($end)) {
            $due = $end;
        }

        do {
            $invoiceNo =
                'HH-'
                . now()->format('Ym')
                . '-'
                . strtoupper(
                    Str::random(8)
                );
        } while (
            HotelBillingInvoice::query()
                ->where(
                    'invoice_no',
                    $invoiceNo
                )
                ->exists()
        );

        return HotelBillingInvoice::query()
            ->create([
                'hotel_id' =>
                    $hotel->id,

                'hotel_subscription_id' =>
                    $subscription->id,

                'invoice_no' =>
                    $invoiceNo,

                'amount' =>
                    $amount,

                'paid_amount' =>
                    0,

                'due_amount' =>
                    $amount,

                'currency' =>
                    $hotel->currency
                    ?: 'QAR',

                'issue_date' =>
                    $start->toDateString(),

                'due_date' =>
                    $due->toDateString(),

                'period_start' =>
                    $start,

                'period_end' =>
                    $end,

                'status' =>
                    $amount > 0
                        ? 'unpaid'
                        : 'paid',

                'paid_at' =>
                    $amount > 0
                        ? null
                        : now(),

                'notes' =>
                    'Automatically generated from Hotel subscription #'
                    . $subscription->id,
            ]);
    }

    public function notify(
        int $hotelId,
        string $type,
        string $severity,
        string $title,
        string $message,
        string $dedupeKey,
        ?string $actionUrl = null,
        array $data = []
    ): HotelNotification {
        return HotelNotification::query()
            ->updateOrCreate(
                [
                    'hotel_id' =>
                        $hotelId,

                    'dedupe_key' =>
                        $dedupeKey,
                ],
                [
                    'type' =>
                        $type,

                    'severity' =>
                        $severity,

                    'title' =>
                        $title,

                    'message' =>
                        $message,

                    'action_url' =>
                        $actionUrl,

                    'data' =>
                        $data,

                    'read_at' =>
                        null,

                    'resolved_at' =>
                        null,
                ]
            );
    }

    public function audit(
        ?int $hotelId,
        ?int $hotelUserId,
        string $actorType,
        ?string $actorName,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $description = null,
        array $metadata = [],
        ?string $ipAddress = null
    ): HotelAuditLog {
        return HotelAuditLog::query()
            ->create([
                'hotel_id' =>
                    $hotelId,

                'hotel_user_id' =>
                    $hotelUserId,

                'actor_type' =>
                    $actorType,

                'actor_name' =>
                    $actorName,

                'action' =>
                    $action,

                'subject_type' =>
                    $subjectType,

                'subject_id' =>
                    $subjectId,

                'description' =>
                    $description,

                'metadata' =>
                    $metadata,

                'ip_address' =>
                    $ipAddress,
            ]);
    }

    public function renewHotel(
        Hotel $hotel,
        ?int $createdBy = null
    ): object {
        return DB::transaction(
            function () use (
                $hotel,
                $createdBy
            ): object {
                $old =
                    DB::table(
                        'hotel_subscriptions'
                    )
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                if (!$old) {
                    throw new \RuntimeException(
                        'Hotel has no subscription to renew.'
                    );
                }

                $planId =
                    $old->hotel_plan_id
                    ?? null;

                $plan =
                    $planId
                        ? DB::table(
                            'hotel_plans'
                        )
                            ->where(
                                'id',
                                $planId
                            )
                            ->first()
                        : null;

                $days =
                    max(
                        1,
                        (int) (
                            $plan
                                ->validity_days
                            ?? 0
                        )
                    );

                if ($days === 1) {
                    try {
                        $oldStart =
                            CarbonImmutable::parse(
                                $old->starts_at
                            );

                        $oldEnd =
                            CarbonImmutable::parse(
                                $old->expires_at
                            );

                        $diff =
                            $oldStart
                                ->diffInDays(
                                    $oldEnd
                                );

                        if ($diff > 1) {
                            $days =
                                (int)
                                $diff;
                        }
                    } catch (\Throwable) {
                        $days = 30;
                    }
                }

                $now =
                    now()
                        ->toImmutable();

                $oldExpiry =
                    !empty(
                        $old->expires_at
                    )
                        ? CarbonImmutable::parse(
                            $old->expires_at
                        )
                        : $now;

                $start =
                    $oldExpiry->gt($now)
                        ? $oldExpiry
                        : $now;

                $end =
                    $start
                        ->addDays(
                            $days
                        );

                DB::table(
                    'hotel_subscriptions'
                )
                    ->where(
                        'hotel_id',
                        $hotel->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->update([
                        'status' =>
                            'renewed',

                        'updated_at' =>
                            now(),
                    ]);

                $columns =
                    Schema::getColumnListing(
                        'hotel_subscriptions'
                    );

                $candidate = [
                    'hotel_id' =>
                        $hotel->id,

                    'hotel_plan_id' =>
                        $planId,

                    'status' =>
                        'active',

                    'guest_limit' =>
                        $old->guest_limit
                        ?? (
                            $plan
                                ->guest_limit
                            ?? null
                        ),

                    'is_guest_unlimited' =>
                        $old->is_guest_unlimited
                        ?? (
                            $plan
                                ->is_guest_unlimited
                            ?? false
                        ),

                    'router_limit' =>
                        $old->router_limit
                        ?? (
                            $plan
                                ->router_limit
                            ?? null
                        ),

                    'is_router_unlimited' =>
                        $old->is_router_unlimited
                        ?? (
                            $plan
                                ->is_router_unlimited
                            ?? false
                        ),

                    'price' =>
                        $old->price
                        ?? (
                            $plan
                                ->price
                            ?? 0
                        ),

                    'starts_at' =>
                        $start,

                    'expires_at' =>
                        $end,

                    'notes' =>
                        'Renewed from subscription #'
                        . $old->id,

                    'created_by' =>
                        $createdBy,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ];

                $insert = [];

                foreach (
                    $candidate
                    as $key => $value
                ) {
                    if (
                        in_array(
                            $key,
                            $columns,
                            true
                        )
                    ) {
                        $insert[$key] =
                            $value;
                    }
                }

                $id =
                    DB::table(
                        'hotel_subscriptions'
                    )
                        ->insertGetId(
                            $insert
                        );

                $hotel->forceFill([
                    'status' =>
                        'active',

                    'suspended_at' =>
                        null,

                    'suspension_reason' =>
                        null,
                ])->save();

                $subscription =
                    DB::table(
                        'hotel_subscriptions'
                    )
                        ->where(
                            'id',
                            $id
                        )
                        ->first();

                $this
                    ->invoiceForSubscription(
                        $subscription
                    );

                return $subscription;
            }
        );
    }

    private function subscriptionAmount(
        object $subscription
    ): float {
        $amount =
            isset(
                $subscription->price
            )
                ? (float)
                    $subscription->price
                : 0.0;

        if ($amount > 0) {
            return $amount;
        }

        $planId =
            $subscription
                ->hotel_plan_id
            ?? null;

        if (
            $planId
            && Schema::hasColumn(
                'hotel_plans',
                'price'
            )
        ) {
            return (float) (
                DB::table(
                    'hotel_plans'
                )
                    ->where(
                        'id',
                        $planId
                    )
                    ->value(
                        'price'
                    )
                ?? 0
            );
        }

        return 0.0;
    }
}
