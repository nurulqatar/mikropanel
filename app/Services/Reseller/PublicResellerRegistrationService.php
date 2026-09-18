<?php

namespace App\Services\Reseller;

use App\Models\Reseller;
use App\Models\ResellerPlan;
use App\Models\ResellerRegistrationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicResellerRegistrationService
{
    public function __construct(
        private ResellerSubscriptionService $subscriptions,
        private ResellerPermissionService $permissions
    ) {
    }

    public function register(
        array $data,
        Request $request
    ): ResellerRegistrationRequest {
        return DB::transaction(
            function () use (
                $data,
                $request
            ): ResellerRegistrationRequest {
                $plan =
                    ResellerPlan::query()
                        ->whereKey(
                            $data['plan_id']
                        )
                        ->where(
                            'active',
                            true
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$plan) {
                    throw ValidationException::withMessages([
                        'plan_id' =>
                            'Selected package is no longer available.',
                    ]);
                }

                $email =
                    strtolower(
                        trim(
                            $data['email']
                        )
                    );

                $phone =
                    trim(
                        (string) (
                            $data['phone']
                            ?? ''
                        )
                    );

                $duplicate =
                    User::query()
                        ->where(
                            'email',
                            $email
                        )
                        ->exists()
                    || Reseller::query()
                        ->where(
                            'email',
                            $email
                        )
                        ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'email' =>
                            'An account already exists with this email address.',
                    ]);
                }

                if (
                    $phone !== ''
                    && (
                        Reseller::query()
                            ->where(
                                'phone',
                                $phone
                            )
                            ->exists()
                        || ResellerRegistrationRequest::query()
                            ->where(
                                'phone',
                                $phone
                            )
                            ->whereIn(
                                'status',
                                [
                                    'pending',
                                    'approved',
                                ]
                            )
                            ->exists()
                    )
                ) {
                    throw ValidationException::withMessages([
                        'phone' =>
                            'This phone number is already connected to a reseller application.',
                    ]);
                }

                $freeTrial =
                    (float) $plan->price <= 0.0001
                    && (int)
                        $plan->validity_days
                        === 7;

                $reseller =
                    Reseller::query()
                        ->create([
                            'code' =>
                                $this
                                    ->nextCode(),

                            'company_name' =>
                                trim(
                                    $data[
                                        'company_name'
                                    ]
                                ),

                            'owner_name' =>
                                trim(
                                    $data[
                                        'owner_name'
                                    ]
                                ),

                            'email' =>
                                $email,

                            'phone' =>
                                $phone !== ''
                                    ? $phone
                                    : null,

                            'address' =>
                                $data[
                                    'address'
                                ] ?? null,

                            'status' =>
                                $freeTrial
                                    ? 'active'
                                    : 'pending',

                            'wallet_balance' =>
                                0,

                            'expiry_mode' =>
                                'panel_lock',

                            'timezone' =>
                                'Asia/Qatar',

                            'currency' =>
                                'QAR',

                            'created_by' =>
                                null,
                        ]);

                $owner =
                    User::query()
                        ->create([
                            'reseller_id' =>
                                $reseller->id,

                            'zone_id' =>
                                null,

                            'staff_role' =>
                                null,

                            'name' =>
                                trim(
                                    $data[
                                        'owner_name'
                                    ]
                                ),

                            'email' =>
                                $email,

                            'email_verified_at' =>
                                now(
                                    'Asia/Qatar'
                                ),

                            'password' =>
                                $data[
                                    'password'
                                ],

                            'role' =>
                                'reseller',

                            'is_super_admin' =>
                                false,

                            'permissions' =>
                                $this
                                    ->permissions
                                    ->ownerPermissions(),

                            'is_active' =>
                                $freeTrial,
                        ]);

                $reseller->forceFill([
                    'owner_user_id' =>
                        $owner->id,
                ])->save();

                $registration =
                    ResellerRegistrationRequest::query()
                        ->create([
                            'public_token' =>
                                (string)
                                Str::uuid(),

                            'reseller_plan_id' =>
                                $plan->id,

                            'reseller_id' =>
                                $reseller->id,

                            'owner_user_id' =>
                                $owner->id,

                            'company_name' =>
                                $reseller
                                    ->company_name,

                            'owner_name' =>
                                $owner->name,

                            'email' =>
                                $email,

                            'phone' =>
                                $reseller->phone,

                            'address' =>
                                $reseller->address,

                            'plan_name_snapshot' =>
                                $plan->name,

                            'plan_price_snapshot' =>
                                $plan->price,

                            'plan_validity_days_snapshot' =>
                                $plan
                                    ->validity_days,

                            'client_limit_snapshot' =>
                                $plan
                                    ->client_limit,

                            'status' =>
                                $freeTrial
                                    ? 'approved'
                                    : 'pending',

                            'auto_approved' =>
                                $freeTrial,

                            'registration_ip' =>
                                $request->ip(),

                            'user_agent' =>
                                Str::limit(
                                    (string)
                                    $request
                                        ->userAgent(),
                                    1000,
                                    ''
                                ),

                            'reviewed_at' =>
                                $freeTrial
                                    ? now(
                                        'Asia/Qatar'
                                    )
                                    : null,

                            'review_notes' =>
                                $freeTrial
                                    ? '7-day free trial automatically approved.'
                                    : null,
                        ]);

                if ($freeTrial) {
                    $this
                        ->subscriptions
                        ->start(
                            $reseller,
                            $plan,
                            $owner->id,
                            7
                        );
                }

                return $registration;
            }
        );
    }

    public function approve(
        ResellerRegistrationRequest $registration,
        User $reviewer
    ): ResellerRegistrationRequest {
        return DB::transaction(
            function () use (
                $registration,
                $reviewer
            ): ResellerRegistrationRequest {
                $locked =
                    ResellerRegistrationRequest::query()
                        ->whereKey(
                            $registration->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                    !== 'pending'
                ) {
                    throw ValidationException::withMessages([
                        'registration' =>
                            'This registration has already been reviewed.',
                    ]);
                }

                $plan =
                    ResellerPlan::query()
                        ->whereKey(
                            $locked
                                ->reseller_plan_id
                        )
                        ->first();

                if (!$plan) {
                    throw ValidationException::withMessages([
                        'registration' =>
                            'The selected package no longer exists.',
                    ]);
                }

                $reseller =
                    Reseller::query()
                        ->whereKey(
                            $locked->reseller_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $owner =
                    User::query()
                        ->whereKey(
                            $locked->owner_user_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $reseller->forceFill([
                    'status' =>
                        'active',

                    'suspended_at' =>
                        null,

                    'suspension_reason' =>
                        null,
                ])->save();

                $owner->forceFill([
                    'is_active' =>
                        true,
                ])->save();

                $this
                    ->subscriptions
                    ->start(
                        $reseller,
                        $plan,
                        $reviewer->id,
                        null
                    );

                $locked->forceFill([
                    'status' =>
                        'approved',

                    'reviewed_by' =>
                        $reviewer->id,

                    'reviewed_at' =>
                        now(
                            'Asia/Qatar'
                        ),

                    'review_notes' =>
                        'Approved by Super Admin.',
                ])->save();

                return $locked->fresh();
            }
        );
    }

    public function reject(
        ResellerRegistrationRequest $registration,
        User $reviewer,
        ?string $reason
    ): ResellerRegistrationRequest {
        return DB::transaction(
            function () use (
                $registration,
                $reviewer,
                $reason
            ): ResellerRegistrationRequest {
                $locked =
                    ResellerRegistrationRequest::query()
                        ->whereKey(
                            $registration->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                    !== 'pending'
                ) {
                    throw ValidationException::withMessages([
                        'registration' =>
                            'This registration has already been reviewed.',
                    ]);
                }

                $reseller =
                    Reseller::query()
                        ->whereKey(
                            $locked->reseller_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $owner =
                    User::query()
                        ->whereKey(
                            $locked->owner_user_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $reseller->forceFill([
                    'status' =>
                        'suspended',

                    'suspended_at' =>
                        now(
                            'Asia/Qatar'
                        ),

                    'suspension_reason' =>
                        $reason
                        ?: 'Public reseller registration rejected.',
                ])->save();

                $owner->forceFill([
                    'is_active' =>
                        false,
                ])->save();

                $locked->forceFill([
                    'status' =>
                        'rejected',

                    'reviewed_by' =>
                        $reviewer->id,

                    'reviewed_at' =>
                        now(
                            'Asia/Qatar'
                        ),

                    'review_notes' =>
                        $reason
                        ?: 'Rejected by Super Admin.',
                ])->save();

                return $locked->fresh();
            }
        );
    }

    private function nextCode(): string
    {
        do {
            $code =
                'RS-'
                . strtoupper(
                    Str::random(10)
                );
        } while (
            Reseller::withTrashed()
                ->where(
                    'code',
                    $code
                )
                ->exists()
        );

        return $code;
    }
}
