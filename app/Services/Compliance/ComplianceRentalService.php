<?php

namespace App\Services\Compliance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ComplianceRentalService
{
    public function ensureCatalog(): void
    {
        $defaults = [
            [
                'name' => 'Compliance Logging',
                'code' => 'compliance-logging-standard',
                'service_type' => 'logging',
                'description' => 'Network metadata logging, NAT/source-port attribution, investigation, retention and external archive.',
            ],
            [
                'name' => 'Network Filtering',
                'code' => 'network-filtering-standard',
                'service_type' => 'filtering',
                'description' => 'Website, domain, IP, server, application and protocol filtering.',
            ],
            [
                'name' => 'Compliance Complete',
                'code' => 'compliance-complete-standard',
                'service_type' => 'bundle',
                'description' => 'Compliance Logging and Network Filtering together.',
            ],
        ];

        /*
         * Existing Super Admin plans always win.
         * Insert a default only when that service
         * type has no plan at all.
         */
        foreach ($defaults as $plan) {
            if (
                DB::table('compliance_plans')
                    ->where(
                        'service_type',
                        $plan['service_type']
                    )
                    ->exists()
            ) {
                continue;
            }

            DB::table('compliance_plans')->insert([
                'name' => $plan['name'],
                'code' => $plan['code'],
                'service_type' => $plan['service_type'],
                'description' => $plan['description'],
                'monthly_price' => 0,
                'call_for_price' => true,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function updatePlan(
        int $planId,
        array $data
    ): void {
        if (
            !DB::table('compliance_plans')
                ->where('id', $planId)
                ->exists()
        ) {
            throw new RuntimeException(
                'Compliance plan not found.'
            );
        }

        DB::table('compliance_plans')
            ->where('id', $planId)
            ->update([
                'name' => $data['name'],
                'monthly_price' => $data['monthly_price'],
                'call_for_price' => (bool) $data['call_for_price'],
                'enabled' => (bool) $data['enabled'],
                'description' => $data['description'] ?? null,
                'updated_at' => now(),
            ]);
    }

    public function rentStandalone(
        array $data,
        ?int $adminId
    ): int {
        return DB::transaction(
            function () use ($data, $adminId): int {
                $code = $this->normalizeCode(
                    $data['organization_code']
                    ?: $data['organization_name']
                );

                if (
                    DB::table('compliance_organizations')
                        ->where('code', $code)
                        ->exists()
                ) {
                    throw new RuntimeException(
                        'Organization code already exists.'
                    );
                }

                $email = strtolower(
                    trim($data['owner_email'])
                );

                if (
                    DB::table('compliance_users')
                        ->where('email', $email)
                        ->exists()
                ) {
                    throw new RuntimeException(
                        'Compliance login email already exists.'
                    );
                }

                $organizationId = DB::table(
                    'compliance_organizations'
                )->insertGetId([
                    'name' => $data['organization_name'],
                    'code' => $code,
                    'account_type' => 'standalone',
                    'legal_profile' => 'private_enterprise',
                    'country_code' => 'QA',
                    'timezone' => 'Asia/Qatar',
                    'contact_name' => $data['owner_name'],
                    'contact_email' => $email,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('compliance_users')->insert([
                    'organization_id' => $organizationId,
                    'name' => $data['owner_name'],
                    'email' => $email,
                    'password' => Hash::make($data['password']),
                    'role' => 'owner',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $this->createRental(
                    organizationId: $organizationId,
                    planId: (int) $data['plan_id'],
                    days: (int) $data['days'],
                    rentalType: 'standalone',
                    sourceType: null,
                    sourceId: null,
                    customerName: $data['organization_name'],
                    amount: (float) $data['amount'],
                    paymentStatus: $data['payment_status'],
                    paymentMethod: $data['payment_method'] ?? null,
                    paymentReference: $data['payment_reference'] ?? null,
                    notes: $data['notes'] ?? null,
                    adminId: $adminId,
                );
            }
        );
    }

    public function rentAddon(
        array $data,
        ?int $adminId
    ): int {
        $sourceType = $data['source_type'];
        $sourceId = (int) $data['source_id'];
        $source = $this->source(
            $sourceType,
            $sourceId
        );

        return DB::transaction(
            function () use (
                $data,
                $adminId,
                $sourceType,
                $sourceId,
                $source
            ): int {
                $grant = DB::table(
                    'compliance_access_grants'
                )
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->latest('id')
                    ->first();

                if ($grant) {
                    $organizationId = (int) $grant->organization_id;
                } else {
                    $code = 'cmp-' . $sourceType . '-' . $sourceId;

                    $organizationId = (int) (
                        DB::table('compliance_organizations')
                            ->where('code', $code)
                            ->value('id')
                        ?? 0
                    );

                    if ($organizationId === 0) {
                        $organizationId = DB::table(
                            'compliance_organizations'
                        )->insertGetId([
                            'name' => $source['name'],
                            'code' => $code,
                            'account_type' => $sourceType,
                            'legal_profile' => 'private_enterprise',
                            'country_code' => 'QA',
                            'timezone' => 'Asia/Qatar',
                            'contact_name' => $source['contact_name'],
                            'contact_email' => $source['contact_email'],
                            'status' => 'active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $rentalId = $this->createRental(
                    organizationId: $organizationId,
                    planId: (int) $data['plan_id'],
                    days: (int) $data['days'],
                    rentalType: 'addon',
                    sourceType: $sourceType,
                    sourceId: $sourceId,
                    customerName: $source['name'],
                    amount: (float) $data['amount'],
                    paymentStatus: $data['payment_status'],
                    paymentMethod: $data['payment_method'] ?? null,
                    paymentReference: $data['payment_reference'] ?? null,
                    notes: $data['notes'] ?? null,
                    adminId: $adminId,
                );

                $plan = DB::table('compliance_plans')
                    ->find((int) $data['plan_id']);

                [$logging, $filtering] = $this->flags(
                    $plan->service_type
                );

                if ($grant) {
                    DB::table('compliance_access_grants')
                        ->where('id', $grant->id)
                        ->update([
                            'organization_id' => $organizationId,
                            'logging_enabled' => $logging,
                            'filtering_enabled' => $filtering,
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('compliance_access_grants')->insert([
                        'organization_id' => $organizationId,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'logging_enabled' => $logging,
                        'filtering_enabled' => $filtering,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $rentalId;
            }
        );
    }

    /*
     * COMPLIANCE_RENTAL_PLAN_CHANGE_V2
     *
     * Changes commercial entitlement only.
     * Existing amount, payment status and expiry are preserved.
     * No RouterOS / collector / storage write is performed here.
     */
    public function changePlan(
        int $rentalId,
        int $planId
    ): void {
        DB::transaction(
            function () use (
                $rentalId,
                $planId
            ): void {
                $rental = DB::table(
                    'compliance_rentals'
                )
                    ->lockForUpdate()
                    ->find($rentalId);

                if (!$rental) {
                    throw new RuntimeException(
                        'Rental not found.'
                    );
                }

                if (
                    !in_array(
                        $rental->status,
                        [
                            'active',
                            'suspended',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Only active or suspended rentals can change plan.'
                    );
                }

                if (!$rental->subscription_id) {
                    throw new RuntimeException(
                        'Rental has no linked subscription.'
                    );
                }

                $subscription = DB::table(
                    'compliance_subscriptions'
                )
                    ->lockForUpdate()
                    ->find(
                        $rental->subscription_id
                    );

                if (
                    !$subscription
                    || (int) $subscription->organization_id
                        !== (int) $rental->organization_id
                ) {
                    throw new RuntimeException(
                        'Linked Compliance subscription is invalid.'
                    );
                }

                $plan = DB::table(
                    'compliance_plans'
                )
                    ->where(
                        'id',
                        $planId
                    )
                    ->where(
                        'enabled',
                        true
                    )
                    ->first();

                if (!$plan) {
                    throw new RuntimeException(
                        'Enabled Compliance plan not found.'
                    );
                }

                [
                    $logging,
                    $filtering,
                ] = $this->flags(
                    $plan->service_type
                );

                DB::table(
                    'compliance_rentals'
                )
                    ->where(
                        'id',
                        $rentalId
                    )
                    ->update([
                        'plan_id' =>
                            $planId,

                        'deployment_status' =>
                            'pending_hardware',

                        'updated_at' =>
                            now(),
                    ]);

                DB::table(
                    'compliance_subscriptions'
                )
                    ->where(
                        'id',
                        $rental->subscription_id
                    )
                    ->update([
                        'plan_id' =>
                            $planId,

                        'logging_enabled' =>
                            $logging,

                        'filtering_enabled' =>
                            $filtering,

                        'updated_at' =>
                            now(),
                    ]);

                if (
                    $rental->source_type
                    && $rental->source_id
                ) {
                    DB::table(
                        'compliance_access_grants'
                    )
                        ->where(
                            'organization_id',
                            $rental->organization_id
                        )
                        ->where(
                            'source_type',
                            $rental->source_type
                        )
                        ->where(
                            'source_id',
                            $rental->source_id
                        )
                        ->update([
                            'logging_enabled' =>
                                $logging,

                            'filtering_enabled' =>
                                $filtering,

                            'is_active' =>
                                $rental->status
                                === 'active',

                            'updated_at' =>
                                now(),
                        ]);
                }

                /*
                 * Intentionally no MikroTik / RouterOS action.
                 * Existing deployed filtering policy is not
                 * silently removed by a commercial plan change.
                 */
            }
        );
    }

    public function renew(
        int $rentalId,
        array $data
    ): void {
        DB::transaction(
            function () use ($rentalId, $data): void {
                $rental = DB::table('compliance_rentals')
                    ->lockForUpdate()
                    ->find($rentalId);

                if (!$rental) {
                    throw new RuntimeException('Rental not found.');
                }

                $base = $rental->expires_at
                    && Carbon::parse($rental->expires_at)->isFuture()
                        ? Carbon::parse($rental->expires_at)
                        : now();

                $expires = $base->copy()->addDays(
                    (int) $data['days']
                );

                DB::table('compliance_rentals')
                    ->where('id', $rentalId)
                    ->update([
                        'amount' => (float) $data['amount'],
                        'payment_status' => $data['payment_status'],
                        'payment_method' => $data['payment_method'] ?? null,
                        'payment_reference' => $data['payment_reference'] ?? null,
                        'status' => 'active',
                        'expires_at' => $expires,
                        'renewed_at' => now(),
                        'suspended_at' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('compliance_subscriptions')
                    ->where('id', $rental->subscription_id)
                    ->update([
                        'status' => 'active',
                        'expires_at' => $expires,
                        'updated_at' => now(),
                    ]);
            }
        );
    }

    public function suspend(
        int $rentalId
    ): void {
        DB::transaction(
            function () use ($rentalId): void {
                $rental = DB::table('compliance_rentals')
                    ->lockForUpdate()
                    ->find($rentalId);

                if (!$rental) {
                    throw new RuntimeException('Rental not found.');
                }

                DB::table('compliance_rentals')
                    ->where('id', $rentalId)
                    ->update([
                        'status' => 'suspended',
                        'suspended_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('compliance_subscriptions')
                    ->where('id', $rental->subscription_id)
                    ->update([
                        'status' => 'suspended',
                        'updated_at' => now(),
                    ]);

                /*
                 * Intentionally no router/filter write.
                 * Last deployed filtering policy remains.
                 */
            }
        );
    }

    public function reactivate(
        int $rentalId,
        int $days = 30
    ): void {
        DB::transaction(
            function () use ($rentalId, $days): void {
                $rental = DB::table('compliance_rentals')
                    ->lockForUpdate()
                    ->find($rentalId);

                if (!$rental) {
                    throw new RuntimeException('Rental not found.');
                }

                $expires = $rental->expires_at
                    && Carbon::parse($rental->expires_at)->isFuture()
                        ? Carbon::parse($rental->expires_at)
                        : now()->addDays($days);

                DB::table('compliance_rentals')
                    ->where('id', $rentalId)
                    ->update([
                        'status' => 'active',
                        'suspended_at' => null,
                        'expires_at' => $expires,
                        'updated_at' => now(),
                    ]);

                DB::table('compliance_subscriptions')
                    ->where('id', $rental->subscription_id)
                    ->update([
                        'status' => 'active',
                        'expires_at' => $expires,
                        'updated_at' => now(),
                    ]);
            }
        );
    }

    public function resetPassword(
        int $organizationId,
        string $email,
        string $password
    ): void {
        $updated = DB::table('compliance_users')
            ->where('organization_id', $organizationId)
            ->where('email', strtolower(trim($email)))
            ->update([
                'password' => Hash::make($password),
                'is_active' => true,
                'updated_at' => now(),
            ]);

        if ($updated < 1) {
            throw new RuntimeException(
                'Standalone Compliance user not found.'
            );
        }
    }

    private function createRental(
        int $organizationId,
        int $planId,
        int $days,
        string $rentalType,
        ?string $sourceType,
        ?int $sourceId,
        string $customerName,
        float $amount,
        string $paymentStatus,
        ?string $paymentMethod,
        ?string $paymentReference,
        ?string $notes,
        ?int $adminId
    ): int {
        $plan = DB::table('compliance_plans')
            ->where('id', $planId)
            ->where('enabled', true)
            ->first();

        if (!$plan) {
            throw new RuntimeException(
                'Enabled Compliance plan not found.'
            );
        }

        [$logging, $filtering] = $this->flags(
            $plan->service_type
        );

        DB::table('compliance_subscriptions')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'trial'])
            ->update([
                'status' => 'replaced',
                'updated_at' => now(),
            ]);

        DB::table('compliance_rentals')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->update([
                'status' => 'replaced',
                'updated_at' => now(),
            ]);

        $starts = now();
        $expires = now()->addDays(max(1, $days));

        $subscriptionId = DB::table(
            'compliance_subscriptions'
        )->insertGetId([
            'organization_id' => $organizationId,
            'plan_id' => $planId,
            'logging_enabled' => $logging,
            'filtering_enabled' => $filtering,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => $starts,
            'expires_at' => $expires,
            'keep_last_filter_policy_on_expiry' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('compliance_rentals')
            ->insertGetId([
                'organization_id' => $organizationId,
                'subscription_id' => $subscriptionId,
                'plan_id' => $planId,
                'rental_type' => $rentalType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'customer_name' => $customerName,
                'amount' => $amount,
                'currency' => 'QAR',
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'status' => 'active',
                'deployment_status' => 'pending_hardware',
                'starts_at' => $starts,
                'expires_at' => $expires,
                'notes' => $notes,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function flags(
        string $serviceType
    ): array {
        return match ($serviceType) {
            'logging' => [true, false],
            'filtering' => [false, true],
            'bundle' => [true, true],
            default => throw new RuntimeException(
                'Unsupported Compliance service type.'
            ),
        };
    }

    private function source(
        string $sourceType,
        int $sourceId
    ): array {
        if ($sourceType === 'reseller') {
            if (!Schema::hasTable('resellers')) {
                throw new RuntimeException(
                    'Company table is unavailable.'
                );
            }

            $row = DB::table('resellers')
                ->where('id', $sourceId)
                ->first();

            if (!$row) {
                throw new RuntimeException('Company not found.');
            }

            return [
                'name' => $row->company_name
                    ?? $row->name
                    ?? ('Company #' . $sourceId),
                'contact_name' => $row->owner_name ?? null,
                'contact_email' => $row->owner_email
                    ?? $row->email
                    ?? null,
            ];
        }

        if ($sourceType === 'hotel') {
            if (!Schema::hasTable('hotels')) {
                throw new RuntimeException(
                    'Hotel table is unavailable.'
                );
            }

            $row = DB::table('hotels')
                ->where('id', $sourceId)
                ->first();

            if (!$row) {
                throw new RuntimeException('Hotel not found.');
            }

            return [
                'name' => $row->name
                    ?? ('Hotel #' . $sourceId),
                'contact_name' => $row->contact_name ?? null,
                'contact_email' => $row->contact_email
                    ?? $row->email
                    ?? null,
            ];
        }

        throw new RuntimeException(
            'Unsupported addon source type.'
        );
    }

    private function normalizeCode(
        string $value
    ): string {
        $code = Str::slug($value);

        return $code !== ''
            ? substr($code, 0, 100)
            : 'cmp-' . strtolower(Str::random(8));
    }
}
