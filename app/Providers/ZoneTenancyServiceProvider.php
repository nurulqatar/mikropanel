<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ClientMonthlyUsage;
use App\Models\ClientRefund;
use App\Models\Expense;
use App\Models\HotspotServer;
use App\Models\HotspotBatch;
use App\Models\HotspotInvoice;
use App\Models\HotspotPayment;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use App\Models\Invoice;
use App\Models\IpRange;
use App\Models\Payment;
use App\Models\Router;
use App\Models\User;
use App\Models\Scopes\HotspotChildZoneScope;
use App\Models\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class ZoneTenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (
            $this->models()
            as $class
        ) {
            $instance =
                new $class();

            if (
                !Schema::hasTable(
                    $instance->getTable()
                )
                || !Schema::hasColumn(
                    $instance->getTable(),
                    'zone_id'
                )
            ) {
                continue;
            }

            $class::addGlobalScope(
                new ZoneScope()
            );

            $class::creating(
                function (
                    Model $model
                ): void {
                    $this->assignZone(
                        $model
                    );
                }
            );

            $class::saving(
                function (
                    Model $model
                ): void {
                    $this->protectOperatorZone(
                        $model
                    );
                }
            );
        }


        /*
         * HOTSPOT_CHILD_ZONE_SCOPE_V1
         *
         * Child Hotspot tables do not carry zone_id.
         * Their zone is inherited through HotspotServer.
         */
        foreach (
            [
                HotspotBatch::class,
                HotspotVoucher::class,
                HotspotSession::class,
                HotspotInvoice::class,
                HotspotPayment::class,
            ]
            as $class
        ) {
            $class::addGlobalScope(
                new HotspotChildZoneScope()
            );
        }

        /*
         * ZONE_STAFF_ASSIGNMENT_V2
         */
        User::creating(
            function (
                User $staff
            ): void {
                if (
                    !$staff->reseller_id
                    || $staff->role
                        !== 'operator'
                ) {
                    return;
                }

                if (
                    $staff->staff_role
                    === 'manager'
                ) {
                    $staff->zone_id =
                        null;

                    return;
                }

                $staff->staff_role =
                    'operator';

                $zoneId =
                    $staff->zone_id
                    ?: $this
                        ->sessionZoneId();

                if (!$zoneId) {
                    $zones =
                        DB::table(
                            'network_zones'
                        )
                            ->where(
                                'reseller_id',
                                $staff->reseller_id
                            )
                            ->where(
                                'enabled',
                                true
                            )
                            ->pluck('id');

                    if (
                        $zones->count()
                        === 1
                    ) {
                        $zoneId =
                            $zones->first();
                    }
                }

                if (!$zoneId) {
                    throw ValidationException::withMessages([
                        'zone_id' =>
                            'Select an active Network Zone before creating an operator.',
                    ]);
                }

                $valid =
                    DB::table(
                        'network_zones'
                    )
                        ->where(
                            'id',
                            $zoneId
                        )
                        ->where(
                            'reseller_id',
                            $staff->reseller_id
                        )
                        ->where(
                            'enabled',
                            true
                        )
                        ->exists();

                if (!$valid) {
                    throw ValidationException::withMessages([
                        'zone_id' =>
                            'Selected operator Network Zone is invalid.',
                    ]);
                }

                $staff->zone_id =
                    (int)
                    $zoneId;
            }
        );

        /*
         * OPERATOR_ZONE_UPDATE_GUARD_V1
         *
         * Every normal reseller operator must stay
         * inside one enabled MAC or Hotspot zone
         * owned by the same reseller.
         */
        User::updating(
            function (
                User $staff
            ): void {
                if (
                    !$staff->reseller_id
                    || $staff->role
                        !== 'operator'
                    || $staff->staff_role
                        === 'manager'
                ) {
                    return;
                }

                $zoneId =
                    (int) (
                        $staff->zone_id
                        ?? 0
                    );

                $valid =
                    $zoneId
                    && DB::table(
                        'network_zones'
                    )
                        ->where(
                            'id',
                            $zoneId
                        )
                        ->where(
                            'reseller_id',
                            $staff->reseller_id
                        )
                        ->where(
                            'enabled',
                            true
                        )
                        ->whereIn(
                            'service_type',
                            [
                                'mac',
                                'hotspot',
                            ]
                        )
                        ->exists();

                if (!$valid) {
                    throw ValidationException::withMessages([
                        'zone_id' =>
                            'Selected operator Network Zone is invalid.',
                    ]);
                }
            }
        );

        /*
         * Managers stay all-zone and may never
         * acquire accounting mutation permissions
         * through the normal Operator editor.
         */
        User::saving(
            function (
                User $staff
            ): void {
                if (
                    !$staff->reseller_id
                    || $staff->staff_role
                        !== 'manager'
                ) {
                    return;
                }

                $staff->role =
                    'operator';

                $staff->zone_id =
                    null;

                $staff->permissions = [
                    'dashboard.view',
                    'clients.view',
                    'clients.create',
                    'clients.edit',
                    'clients.renew',
                    'packages.view',
                    'ip_pools.view',
                    'invoices.view',
                    'invoices.export',
                    'payments.view',
                    'payments.manage',
                    'expenses.view',
                    'expenses.manage',
                    'accounting.view',
                    'accounting.export',
                    'hotspot.view',
                    'hotspot.export',
                ];
            }
        );
}

    private function assignZone(
        Model $model
    ): void {
        $user =
            Auth::user();

        $zoneId =
            $model->getAttribute(
                'zone_id'
            );

        $zoneId =
            $zoneId === null
                ? null
                : (int) $zoneId;

        /*
         * Finance follows client zone.
         */
        if (
            $zoneId === null
            && (
                $model instanceof Invoice
                || $model instanceof Payment
                || $model instanceof ClientRefund
                || $model instanceof ClientMonthlyUsage
            )
            && $model->client_id
        ) {
            $zoneId =
                $this->clientZone(
                    (int)
                    $model->client_id
                );
        }

        /*
         * Additional device follows primary.
         */
        if (
            $zoneId === null
            && $model instanceof Client
            && $model->parent_client_id
        ) {
            $zoneId =
                $this->clientZone(
                    (int)
                    $model->parent_client_id
                );
        }

        /*
         * Client follows selected panel IP Pool.
         */
        if (
            $zoneId === null
            && $model instanceof Client
            && $model->ip_range_id
        ) {
            $zoneId =
                $this->rangeZone(
                    (int)
                    $model->ip_range_id
                );
        }

        /*
         * Legacy IP Pool/router relationship.
         */
        if (
            $zoneId === null
            && $model instanceof IpRange
            && $model->router_id
        ) {
            $zoneId =
                $this->routerZone(
                    (int)
                    $model->router_id
                );
        }

        /*
         * Zone operator cannot select another site.
         */
        if (
            $user
            && $user->reseller_id
            && $user->role !== 'reseller'
            && !$user->isManager()
        ) {
            if (!$user->zone_id) {
                throw
                    ValidationException::withMessages([
                        'zone_id' =>
                            'This operator has no network zone assignment.',
                    ]);
            }

            if (
                $zoneId !== null
                && (int) $zoneId
                    !== (int)
                    $user->zone_id
            ) {
                throw
                    ValidationException::withMessages([
                        'zone_id' =>
                            'Cross-zone operation is not allowed.',
                    ]);
            }

            $zoneId =
                (int)
                $user->zone_id;
        }

        /*
         * Existing system remains usable while
         * only one compatible default zone exists.
         */
        /*
         * ACTIVE_ZONE_CONTEXT_V2
         *
         * Owner / manager chooses the current
         * remote site from Network Zones.
         */
        if (
            $zoneId === null
            && $user
            && $user->reseller_id
            && (
                $user->role === 'reseller'
                || $user->isManager()
            )
        ) {
            $selected =
                $this
                    ->sessionZoneId();

            if ($selected) {
                $zoneId =
                    $selected;
            }
        }

        if ($zoneId === null) {
            $zoneId =
                $this->defaultZone(
                    $model,
                    $user?->reseller_id
                );
        }

        /*
         * REQUIRE_RESELLER_ZONE_V2
         */
        if (
            $zoneId === null
            && $user
            && $user->reseller_id
            && (
                $model instanceof Router
                || $model instanceof IpRange
                || $model instanceof HotspotServer
                || $model instanceof Expense
            )
        ) {
            throw ValidationException::withMessages([
                'zone_id' =>
                    'Select an active Network Zone before creating this record.',
            ]);
        }

        if ($zoneId === null) {
            return;
        }

        $this->validateZone(
            $model,
            $zoneId,
            $user?->reseller_id
        );

        $model->setAttribute(
            'zone_id',
            $zoneId
        );
    }

    private function protectOperatorZone(
        Model $model
    ): void {
        if (!$model->exists) {
            return;
        }

        $user =
            Auth::user();

        if (
            !$user
            || !$user->reseller_id
            || $user->role === 'reseller'
            || $user->isManager()
        ) {
            return;
        }

        $operatorZone =
            (int) (
                $user->zone_id
                ?? 0
            );

        $original =
            (int) (
                $model->getRawOriginal(
                    'zone_id'
                )
                ?? 0
            );

        $current =
            (int) (
                $model->getAttribute(
                    'zone_id'
                )
                ?? 0
            );

        if (
            !$operatorZone
            || $original !== $operatorZone
            || $current !== $operatorZone
        ) {
            throw
                ValidationException::withMessages([
                    'zone_id' =>
                        'Cross-zone modification is not allowed.',
                ]);
        }
    }

    private function clientZone(
        int $id
    ): ?int {
        $value =
            DB::table(
                'clients'
            )
                ->where(
                    'id',
                    $id
                )
                ->value(
                    'zone_id'
                );

        return $value === null
            ? null
            : (int) $value;
    }

    private function rangeZone(
        int $id
    ): ?int {
        $value =
            DB::table(
                'ip_ranges'
            )
                ->where(
                    'id',
                    $id
                )
                ->value(
                    'zone_id'
                );

        return $value === null
            ? null
            : (int) $value;
    }

    private function routerZone(
        int $id
    ): ?int {
        $value =
            DB::table(
                'routers'
            )
                ->where(
                    'id',
                    $id
                )
                ->value(
                    'zone_id'
                );

        return $value === null
            ? null
            : (int) $value;
    }

    private function defaultZone(
        Model $model,
        ?int $resellerId
    ): ?int {
        $type = null;

        if (
            $model instanceof Router
            || $model instanceof IpRange
            || $model instanceof Client
            || $model instanceof Expense
        ) {
            $type = 'mac';
        }

        if (
            $model instanceof HotspotServer
        ) {
            $type = 'hotspot';
        }

        if (!$type) {
            return null;
        }

        $query =
            DB::table(
                'network_zones'
            )
                ->where(
                    'service_type',
                    $type
                )
                ->where(
                    'enabled',
                    true
                );

        if ($resellerId === null) {
            $query->whereNull(
                'reseller_id'
            );
        } else {
            $query->where(
                'reseller_id',
                $resellerId
            );
        }

        $zones =
            $query
                ->orderBy('id')
                ->pluck('id');

        return $zones->count() === 1
            ? (int)
                $zones->first()
            : null;
    }

    private function validateZone(
        Model $model,
        int $zoneId,
        ?int $authenticatedReseller
    ): void {
        $zone =
            DB::table(
                'network_zones'
            )
                ->where(
                    'id',
                    $zoneId
                )
                ->first();

        if (
            !$zone
            || !$zone->enabled
        ) {
            throw
                ValidationException::withMessages([
                    'zone_id' =>
                        'Selected network zone is not available.',
                ]);
        }

        $type = null;

        /*
         * ROUTER_DUAL_SERVICE_ZONE_V1
         *
         * Router itself may belong to either a
         * MAC or Hotspot zone. Child MAC resources
         * remain MAC-only.
         */
        if (
            $model instanceof IpRange
            || $model instanceof Client
        ) {
            $type = 'mac';
        }

        if (
            $model instanceof HotspotServer
        ) {
            $type = 'hotspot';
        }

        if (
            $type
            && $zone->service_type
                !== $type
        ) {
            throw
                ValidationException::withMessages([
                    'zone_id' =>
                        'Selected network zone has the wrong service type.',
                ]);
        }

        $tenant =
            $model->getAttribute(
                'reseller_id'
            )
            ?? $authenticatedReseller;

        /*
         * Background finance creation:
         * derive tenant directly from client.
         */
        if (
            $tenant === null
            && (
                $model instanceof Invoice
                || $model instanceof Payment
                || $model instanceof ClientRefund
                || $model instanceof ClientMonthlyUsage
            )
            && $model->client_id
        ) {
            $tenant =
                DB::table(
                    'clients'
                )
                    ->where(
                        'id',
                        $model->client_id
                    )
                    ->value(
                        'reseller_id'
                    );
        }

        if ($tenant === null) {
            if (
                $zone->reseller_id
                !== null
            ) {
                throw
                    ValidationException::withMessages([
                        'zone_id' =>
                            'Cross-reseller zone selection is not allowed.',
                    ]);
            }

            return;
        }

        if (
            (int)
            $zone->reseller_id
            !== (int)
            $tenant
        ) {
            throw
                ValidationException::withMessages([
                    'zone_id' =>
                        'Cross-reseller zone selection is not allowed.',
                ]);
        }
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

        $request =
            request();

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

    private function models(): array
    {
        return [
            Client::class,
            Router::class,
            IpRange::class,
            Invoice::class,
            Payment::class,
            ClientRefund::class,
            Expense::class,
            ClientMonthlyUsage::class,
            HotspotServer::class,
        ];
    }
}
