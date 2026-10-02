<?php

namespace App\Services;

use App\Exceptions\ClientProvisioningException;
use App\Models\Client;
use App\Models\ClientRouterBinding;
use App\Models\IpRange;
use App\Models\Router;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientProvisionService
{
    public function __construct(
        protected DhcpLeaseService $dhcpLeaseService,
        protected ArpService $arpService,
        protected QueueService $queueService,
        protected IpAllocatorService $ipAllocatorService
    ) {
    }

    /*
     * New client:
     * save one global client/IP in panel,
     * then converge that state across every
     * enabled MikroTik independently.
     */
    public function provision(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $this->syncAcrossEnabledRouters(
            $client
        );

        $this->syncLegacyPrimaryIds(
            $client
        );
    }

    /*
     * Existing client update.
     *
     * Router failures are NOT allowed to stop
     * the panel update. Failed routers remain
     * marked for automatic retry.
     */
    public function update(
        Client $client,
        ?Client $rollbackClient = null
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $this->syncAcrossEnabledRouters(
            $client
        );
    }

    public function suspend(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $desired = clone $client;

        $desired->setRelations(
            $client->getRelations()
        );

        $desired->forceFill([
            'enabled' => false,
            'connected' => false,
        ]);

        $this->syncAcrossEnabledRouters(
            $desired
        );

        try {
            $client->forceFill([
                'enabled' => false,
                'connected' => false,
            ])->save();

            $this->syncLegacyPrimaryIds(
                $client
            );

        } catch (Throwable $exception) {
            throw new ClientProvisioningException(
                'Unable to save suspended client state.',
                false,
                $exception
            );
        }
    }

    public function unsuspend(
        Client $client
    ): void {

        /*
         * REFUNDED_CLIENT_ACTIVATION_LOCK_V1
         *
         * A refunded service is permanently locked from
         * free/manual activation.
         *
         * It may be activated again only after a NEW,
         * fully-paid, non-cancelled service invoice is
         * created AFTER the latest refund.
         *
         * This guard lives in the provisioning service,
         * so manual Activate, POS, renewal and any other
         * code path calling unsuspend() cannot bypass it.
         */
        /*
         * ACCOUNT_DUE_FAMILY_ACTIVATION_LOCK_V1
         *
         * A due-mode refund is an account-level service
         * termination. Every device in the family was
         * suspended, so no sibling may be manually
         * activated for free afterwards.
         *
         * Each device unlocks independently only after
         * that device receives a NEW, fully-paid service
         * renewal backed by real Payment ledger money.
         */
        $latestFamilyDueRefund =
            $this->latestAccountDueFamilyRefund(
                $client
            );

        if ($latestFamilyDueRefund) {
            $familyRefundMoment =
                $latestFamilyDueRefund
                    ->created_at
                ?? $latestFamilyDueRefund
                    ->refund_date
                ?? null;

            $hasPaidRenewalAfterFamilyRefund =
                $this->hasFullyPaidRenewalAfter(
                    $client,
                    $familyRefundMoment
                );

            if (
                !$hasPaidRenewalAfterFamilyRefund
            ) {
                /*
                 * Defense in depth:
                 * keep local flags suspended before
                 * returning the hard-lock exception.
                 */
                $client->forceFill([
                    'enabled' => false,
                    'connected' => false,
                ])->save();

                throw new
                    ClientProvisioningException(
                        'ACCOUNT_REFUND_LOCK: This customer account was closed by a due-mode refund. Receive full payment and create a new renewal for this device before activation.',
                        true
                    );
            }
        }

        $latestRefund =
            \App\Models\ClientRefund::withoutGlobalScopes()
                ->where(
                    'client_id',
                    $client->id
                )
                ->orderByDesc('id')
                ->first();

        if ($latestRefund) {
            /*
             * REFUNDED_CLIENT_PAYMENT_LEDGER_V2
             *
             * A "paid" invoice flag alone is NOT enough.
             *
             * Unlock requires:
             * 1. a NEW service invoice after the refund;
             * 2. invoice status fully paid with zero due;
             * 3. service was not cancelled/refunded;
             * 4. actual Payment ledger entries created
             *    after the refund cover the full net price.
             *
             * Minimum actual payment is QAR 0.01, so a
             * refunded device cannot be reactivated using
             * a zero-price/manual paid invoice.
             */
            $refundMoment =
                $latestRefund->created_at
                ?? $latestRefund->refund_date
                ?? null;

            $paidRenewalInvoices =
                \App\Models\Invoice::withoutGlobalScopes()
                    ->where(
                        'client_id',
                        $client->id
                    )
                    ->where(
                        'id',
                        '>',
                        (int)
                        $latestRefund->invoice_id
                    )
                    ->where(
                        'applies_service_period',
                        true
                    )
                    ->where(
                        'status',
                        'paid'
                    )
                    ->where(
                        'due_amount',
                        '<=',
                        0
                    )
                    ->whereNull(
                        'service_cancelled_at'
                    )
                    ->when(
                        $refundMoment,
                        function (
                            $query
                        ) use (
                            $refundMoment
                        ): void {
                            $query->where(
                                'created_at',
                                '>',
                                $refundMoment
                            );
                        }
                    )
                    ->orderByDesc('id')
                    ->get([
                        'id',
                        'amount',
                        'discount',
                        'created_at',
                    ]);

            $hasPaidRenewalAfterRefund =
                $paidRenewalInvoices
                    ->contains(
                        function (
                            $invoice
                        ) use (
                            $client,
                            $refundMoment
                        ): bool {
                            $netPrice = round(
                                max(
                                    0,
                                    (float)
                                    $invoice->amount
                                    -
                                    (float)
                                    $invoice->discount
                                ),
                                2
                            );

                            /*
                             * Even a zero-price invoice
                             * cannot unlock a refunded
                             * device for free.
                             */
                            $requiredPayment =
                                max(
                                    0.01,
                                    $netPrice
                                );

                            $paidAmount =
                                round(
                                    (float)
                                    \App\Models\Payment::withoutGlobalScopes()
                                        ->where(
                                            'client_id',
                                            $client->id
                                        )
                                        ->where(
                                            'invoice_id',
                                            $invoice->id
                                        )
                                        ->when(
                                            $refundMoment,
                                            function (
                                                $query
                                            ) use (
                                                $refundMoment
                                            ): void {
                                                $query->where(
                                                    'created_at',
                                                    '>',
                                                    $refundMoment
                                                );
                                            }
                                        )
                                        ->sum(
                                            'amount'
                                        ),
                                    2
                                );

                            return
                                $paidAmount
                                >=
                                $requiredPayment;
                        }
                    );

            if (!$hasPaidRenewalAfterRefund) {
                /*
                 * Defense in depth:
                 * even if another code path changed the
                 * local flags, force the refunded device
                 * back to suspended state before exiting.
                 */
                $client->forceFill([
                    'enabled' => false,
                    'connected' => false,
                ])->save();

                throw new
                    ClientProvisioningException(
                        'REFUND_LOCK: This device was refunded. Receive full payment and create a new renewal before activation.',
                        true
                    );
            }
        }

        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $desired = clone $client;

        $desired->setRelations(
            $client->getRelations()
        );

        $desired->forceFill([
            'enabled' => true,
        ]);

        $this->syncAcrossEnabledRouters(
            $desired
        );

        try {
            $client->forceFill([
                'enabled' => true,
            ])->save();

            $this->syncLegacyPrimaryIds(
                $client
            );

        } catch (Throwable $exception) {
            throw new ClientProvisioningException(
                'Unable to save active client state.',
                false,
                $exception
            );
        }
    }

    /*
     * Locate the latest account-level due-mode refund.
     *
     * The machine marker is written only by the
     * due-mode refund transaction.
     */
    private function latestAccountDueFamilyRefund(
        Client $client
    ): ?\App\Models\ClientRefund {
        $primaryId =
            (int) (
                $client
                    ->parent_client_id
                ?: $client->id
            );

        $familyQuery =
            Client::withoutGlobalScopes()
                ->whereNull(
                    'deleted_at'
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $primaryId
                    ): void {
                        $query
                            ->whereKey(
                                $primaryId
                            )
                            ->orWhere(
                                'parent_client_id',
                                $primaryId
                            );
                    }
                )
                ->orderBy('id');

        /*
         * Never allow a corrupted parent link to
         * cross reseller/zone tenancy boundaries.
         */
        if (
            $client
                ->reseller_id
            === null
        ) {
            $familyQuery
                ->whereNull(
                    'reseller_id'
                );
        } else {
            $familyQuery
                ->where(
                    'reseller_id',
                    $client
                        ->reseller_id
                );
        }

        if (
            $client
                ->zone_id
            === null
        ) {
            $familyQuery
                ->whereNull(
                    'zone_id'
                );
        } else {
            $familyQuery
                ->where(
                    'zone_id',
                    $client
                        ->zone_id
                );
        }

        $familyClientIds =
            $familyQuery
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->values();

        if (
            $familyClientIds
                ->isEmpty()
        ) {
            return null;
        }

        return
            \App\Models\ClientRefund::withoutGlobalScopes()
                ->whereIn(
                    'client_id',
                    $familyClientIds
                )
                ->where(
                    'reason',
                    'like',
                    '%[ACCOUNT_DUE_FAMILY_LOCK_V1]%'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc('id')
                ->first();
    }

    /*
     * A family-refund lock is released for ONE device
     * only when that same device has a new paid service
     * invoice after the family refund and real payments
     * after that refund cover its full net service price.
     */
    private function hasFullyPaidRenewalAfter(
        Client $client,
        mixed $refundMoment
    ): bool {
        if (!$refundMoment) {
            return false;
        }

        $paidRenewalInvoices =
            \App\Models\Invoice::withoutGlobalScopes()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'applies_service_period',
                    true
                )
                ->where(
                    'status',
                    'paid'
                )
                ->where(
                    'due_amount',
                    '<=',
                    0
                )
                ->whereNull(
                    'service_cancelled_at'
                )
                ->where(
                    'created_at',
                    '>',
                    $refundMoment
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'amount',
                    'discount',
                    'created_at',
                ]);

        return
            $paidRenewalInvoices
                ->contains(
                    function (
                        $invoice
                    ) use (
                        $client,
                        $refundMoment
                    ): bool {
                        $netPrice =
                            round(
                                max(
                                    0,
                                    (float)
                                    $invoice
                                        ->amount
                                    -
                                    (float)
                                    $invoice
                                        ->discount
                                ),
                                2
                            );

                        /*
                         * Zero-price/manual invoices
                         * can never unlock the device.
                         */
                        $requiredPayment =
                            max(
                                0.01,
                                $netPrice
                            );

                        $paidAmount =
                            round(
                                (float)
                                \App\Models\Payment::withoutGlobalScopes()
                                    ->where(
                                        'client_id',
                                        $client->id
                                    )
                                    ->where(
                                        'invoice_id',
                                        $invoice->id
                                    )
                                    ->where(
                                        'created_at',
                                        '>',
                                        $refundMoment
                                    )
                                    ->sum(
                                        'amount'
                                    ),
                                2
                            );

                        return
                            $paidAmount
                            >=
                            $requiredPayment;
                    }
                );
    }

    /*
     * Archive cleanup.
     *
     * Reachable routers are cleaned now.
     * Offline routers remain failed and the
     * background command retries later.
     */
    public function remove(
        Client $client
    ): void {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        $bindings = ClientRouterBinding::query()
            ->where(
                'client_id',
                $client->id
            )
            ->get();

        foreach ($bindings as $binding) {
            $router =
                Router::withoutGlobalScopes()
                    ->find(
                        $binding->router_id
                    );

            if (!$router) {
                continue;
            }

            /*
             * ROAMING_REMOVE_TENANT_BOUNDARY_V1
             */
            if (
                !$this->sameTenant(
                    $client,
                    $router
                )
            ) {
                Log::error(
                    'Cross-reseller client binding was not touched.',
                    [
                        'client_id' =>
                            $client->id,

                        'router_id' =>
                            $router->id,
                    ]
                );

                continue;
            }

            $this->removeFromRouter(
                $client,
                $router,
                $binding
            );
        }

        $client->forceFill([
            'mikrotik_lease_id' => null,
            'mikrotik_arp_id' => null,
            'mikrotik_queue_id' => null,
        ])->save();
    }

    /*
     * TRANSFER_SOURCE_ZONE_CLEANUP_V1
     *
     * Remove a client's OLD MAC/IP state only
     * from routers belonging to the source zone.
     *
     * This deliberately bypasses the current
     * operator ZoneScope because transfer approval
     * is performed by the DESTINATION operator.
     *
     * No client zone/database ownership is changed
     * here. The transfer service changes ownership
     * only after source-router cleanup succeeds.
     */
    public function removeFromZone(
        Client $client,
        int $zoneId
    ): bool {
        if (
            !$client->id
            || $zoneId < 1
        ) {
            return false;
        }

        /*
         * Destination operator cannot normally load
         * the old source IP Pool through ZoneScope.
         * Supply the source relation explicitly.
         */
        if ($client->ip_range_id) {
            $sourceRange =
                IpRange::withoutGlobalScopes()
                    ->find(
                        $client->ip_range_id
                    );

            if ($sourceRange) {
                $client->setRelation(
                    'ipRange',
                    $sourceRange
                );
            }
        }

        $routerIds =
            Router::withoutGlobalScopes()
                ->where(
                    'zone_id',
                    $zoneId
                )
                ->pluck(
                    'id'
                );

        if ($routerIds->isEmpty()) {
            return true;
        }

        $bindings =
            ClientRouterBinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->whereIn(
                    'router_id',
                    $routerIds
                )
                ->get();

        $ok = true;

        foreach (
            $bindings
            as $binding
        ) {
            $router =
                Router::withoutGlobalScopes()
                    ->find(
                        $binding->router_id
                    );

            /*
             * Router record disappeared from panel,
             * therefore there is no managed router
             * left to clean.
             */
            if (!$router) {
                continue;
            }

            if (
                !$this->removeFromRouter(
                    $client,
                    $router,
                    $binding
                )
            ) {
                $ok = false;
            }
        }

        return $ok;
    }

    /*
     * Public entry used by automatic retry
     * command and future RouterController hook.
     */
    /*
     * ROAMING_PACKAGE_PROVISION_V1
     *
     * Home-zone packages may touch only their own
     * zone. All-zone packages may touch any enabled
     * router belonging to the SAME reseller.
     */
    public function syncClientToRouter(
        Client $client,
        Router $router
    ): bool {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        /*
         * Hard tenant boundary.
         * Never perform a network action across
         * reseller ownership.
         */
        if (
            !$this->sameTenant(
                $client,
                $router
            )
        ) {
            Log::error(
                'Cross-reseller client/router sync blocked.',
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,
                ]
            );

            return false;
        }

        if (
            !$client->zone_id
            || !$router->zone_id
        ) {
            Log::warning(
                'Client/router zone is missing.',
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,
                ]
            );

            return false;
        }

        /*
         * A stale roaming binding must be removed
         * after an all-zone package is changed back
         * to home-zone.
         */
        if (
            !$this->routerAllowed(
                $client,
                $router
            )
        ) {
            $binding =
                ClientRouterBinding::query()
                    ->where(
                        'client_id',
                        $client->id
                    )
                    ->where(
                        'router_id',
                        $router->id
                    )
                    ->first();

            if (
                !$binding
                || $binding->sync_status
                    === 'removed'
            ) {
                return true;
            }

            return $this->removeFromRouter(
                $client,
                $router,
                $binding
            );
        }

        if ($client->trashed()) {
            $binding =
                ClientRouterBinding::query()
                    ->where(
                        'client_id',
                        $client->id
                    )
                    ->where(
                        'router_id',
                        $router->id
                    )
                    ->first();

            if (!$binding) {
                return true;
            }

            return $this->removeFromRouter(
                $client,
                $router,
                $binding
            );
        }

        $ok =
            $this->syncOneRouter(
                $client,
                $router
            );

        if (
            $ok
            && (int) $client->router_id
                === (int) $router->id
        ) {
            $this->syncLegacyPrimaryIds(
                $client
            );
        }

        return $ok;
    }

    private function syncAcrossEnabledRouters(
        Client $client
    ): array {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        /*
         * ROAMING_ROUTER_FANOUT_V1
         *
         * home_zone:
         *   only enabled routers in client.zone_id
         *
         * all_zones:
         *   every enabled router of same reseller
         *
         * Query deliberately bypasses the logged-in
         * Operator ZoneScope, then re-applies the
         * reseller boundary explicitly.
         */
        $query =
            Router::withoutGlobalScopes()
                ->where(
                    'enabled',
                    true
                );

        if (
            $client->reseller_id
            === null
        ) {
            $query->whereNull(
                'reseller_id'
            );
        } else {
            $query->where(
                'reseller_id',
                $client->reseller_id
            );
        }

        if (
            $this->coverageMode(
                $client
            ) !== 'all_zones'
        ) {
            $query->where(
                'zone_id',
                $client->zone_id
            );
        }

        $routers =
            $query
                ->orderBy('id')
                ->get();

        $desiredRouterIds =
            $routers
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();

        /*
         * Remove no-longer-authorized roaming state.
         */
        $existingBindings =
            ClientRouterBinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->get();

        foreach (
            $existingBindings
            as $binding
        ) {
            if (
                in_array(
                    (int) $binding->router_id,
                    $desiredRouterIds,
                    true
                )
                || $binding->sync_status
                    === 'removed'
            ) {
                continue;
            }

            $router =
                Router::withoutGlobalScopes()
                    ->find(
                        $binding->router_id
                    );

            if (!$router) {
                continue;
            }

            if (
                !$this->sameTenant(
                    $client,
                    $router
                )
            ) {
                $binding->forceFill([
                    'sync_status' =>
                        'failed',

                    'last_error' =>
                        'Cross-reseller stale binding blocked.',
                ])->save();

                continue;
            }

            $this->removeFromRouter(
                $client,
                $router,
                $binding
            );
        }

        $result = [
            'synced' => 0,
            'failed' => 0,
        ];

        foreach ($routers as $router) {
            if (
                $this->syncClientToRouter(
                    $client,
                    $router
                )
            ) {
                $result['synced']++;
            } else {
                $result['failed']++;
            }
        }

        if ($routers->isEmpty()) {
            Log::warning(
                'Client sync found no eligible enabled routers.',
                [
                    'client_id' =>
                        $client->id,

                    'home_zone_id' =>
                        $client->zone_id,

                    'coverage_mode' =>
                        $this->coverageMode(
                            $client
                        ),
                ]
            );
        }

        return $result;
    }

    private function syncOneRouter(
        Client $client,
        Router $router
    ): bool {
        $binding =
            ClientRouterBinding::firstOrCreate(
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,
                ],
                [
                    'sync_status' =>
                        'pending',
                ]
            );

        $binding->forceFill([
            'sync_status' => 'pending',
            'last_error' => null,
        ])->save();

        /*
         * Every target zone gets its own local
         * IP Pool / IP address.
         */
        if (
            !$this->prepareBindingNetwork(
                $client,
                $router,
                $binding
            )
        ) {
            return false;
        }

        try {
            $context = $this->context(
                $client,
                $router,
                $binding
            );

            /*
             * CREATE calls are idempotent:
             * existing objects are found by
             * client_code / queue name.
             */
            $leaseId =
                $this->dhcpLeaseService
                    ->create($context);

            $binding->forceFill([
                'mikrotik_lease_id' =>
                    $leaseId,
            ])->save();

            $context->forceFill([
                'mikrotik_lease_id' =>
                    $leaseId,
            ]);

            $arpId =
                $this->arpService
                    ->create($context);

            $binding->forceFill([
                'mikrotik_arp_id' =>
                    $arpId,
            ])->save();

            $context->forceFill([
                'mikrotik_arp_id' =>
                    $arpId,
            ]);

            $queueId =
                $this->queueService
                    ->create($context);

            $binding->forceFill([
                'mikrotik_queue_id' =>
                    $queueId,
            ])->save();

            $context->forceFill([
                'mikrotik_queue_id' =>
                    $queueId,
            ]);

            /*
             * Force current MAC/IP/package
             * values even when objects already
             * existed before this sync.
             */
            $this->dhcpLeaseService
                ->update($context);

            $this->arpService
                ->update($context);

            $this->queueService
                ->update($context);

            /*
             * Desired enabled state is also
             * converged on every router.
             */
            if ($client->enabled) {
                $this->dhcpLeaseService
                    ->enable($context);

                $this->arpService
                    ->enable($context);

                $this->queueService
                    ->enable($context);

            } else {
                $this->queueService
                    ->disable($context);

                $this->arpService
                    ->disable($context);

                $this->dhcpLeaseService
                    ->disable($context);
            }

            $binding->forceFill([
                'sync_status' =>
                    'synced',

                'last_synced_at' =>
                    now(),

                'last_error' =>
                    null,
            ])->save();

            return true;

        } catch (Throwable $exception) {
            $binding->forceFill([
                'sync_status' =>
                    'failed',

                'last_error' =>
                    $exception
                        ->getMessage(),
            ])->save();

            Log::warning(
                'Client router sync failed.',
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,

                    'router_name' =>
                        $router->name,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return false;
        }
    }

    private function removeFromRouter(
        Client $client,
        Router $router,
        ClientRouterBinding $binding
    ): bool {
        $context = $this->context(
            $client,
            $router,
            $binding
        );

        $errors = [];

        foreach (
            [
                $this->queueService,
                $this->arpService,
                $this->dhcpLeaseService,
            ]
            as $service
        ) {
            try {
                $service->remove(
                    $context
                );

            } catch (Throwable $exception) {
                $errors[] =
                    $service::class
                    . ': '
                    . $exception
                        ->getMessage();
            }
        }

        if ($errors !== []) {
            $binding->forceFill([
            /*
             * ROAMING_BINDING_NETWORK_RELEASE_V1
             *
             * A removed roaming access mapping no
             * longer reserves the target-zone IP.
             */
            'zone_id' =>
                null,

            'ip_range_id' =>
                null,

            'ip_address' =>
                null,

                'sync_status' =>
                    'failed',

                'last_error' =>
                    implode(
                        ' | ',
                        $errors
                    ),
            ])->save();

            Log::warning(
                'Archived client router cleanup failed.',
                [
                    'client_id' =>
                        $client->id,

                    'router_id' =>
                        $router->id,

                    'errors' =>
                        $errors,
                ]
            );

            return false;
        }

        $binding->forceFill([
            'mikrotik_lease_id' =>
                null,

            'mikrotik_arp_id' =>
                null,

            'mikrotik_queue_id' =>
                null,

            'sync_status' =>
                'removed',

            'last_synced_at' =>
                now(),

            'last_error' =>
                null,
        ])->save();

        return true;
    }

    private function context(
        Client $source,
        Router $router,
        ClientRouterBinding $binding
    ): Client {
        $context = clone $source;

        $context->setRelations(
            $source->getRelations()
        );

        $range =
            $binding->ip_range_id
                ? \App\Models\IpRange::withoutGlobalScopes()
                    ->find(
                        $binding->ip_range_id
                    )
                : null;

        $context->forceFill([
            /*
             * ROAMING_BINDING_CONTEXT_V1
             *
             * Runtime clone only.
             * Source client keeps the original
             * billing/home-zone network fields.
             */
            'zone_id' =>
                $binding->zone_id
                ?: $source->zone_id,

            'router_id' =>
                $router->id,

            'ip_range_id' =>
                $binding->ip_range_id
                ?: $source->ip_range_id,

            'ip_address' =>
                $binding->ip_address
                ?: $source->ip_address,

            'mikrotik_lease_id' =>
                $binding
                    ->mikrotik_lease_id,

            'mikrotik_arp_id' =>
                $binding
                    ->mikrotik_arp_id,

            'mikrotik_queue_id' =>
                $binding
                    ->mikrotik_queue_id,
        ]);

        $context->setRelation(
            'router',
            $router
        );

        if ($range) {
            $context->setRelation(
                'ipRange',
                $range
            );
        }

        return $context;
    }

    private function coverageMode(
        Client $client
    ): string {
        $client->loadMissing(
            'package'
        );

        return $client
            ->package
            ?->coverage_mode
            === 'all_zones'
                ? 'all_zones'
                : 'home_zone';
    }

    private function sameTenant(
        Client $client,
        Router $router
    ): bool {
        $clientTenant =
            $client->reseller_id
            === null
                ? null
                : (int)
                    $client
                        ->reseller_id;

        $routerTenant =
            $router->reseller_id
            === null
                ? null
                : (int)
                    $router
                        ->reseller_id;

        return $clientTenant
            === $routerTenant;
    }

    private function routerAllowed(
        Client $client,
        Router $router
    ): bool {
        if (
            !$this->sameTenant(
                $client,
                $router
            )
            || !$client->zone_id
            || !$router->zone_id
            || !$router->enabled
        ) {
            return false;
        }

        if (
            $this->coverageMode(
                $client
            ) === 'all_zones'
        ) {
            return true;
        }

        return (int)
            $client->zone_id
            === (int)
                $router->zone_id;
    }

    /*
     * One client uses ONE local IP per Network Zone.
     *
     * If the zone has multiple MikroTik routers,
     * their bindings reuse that same zone-local IP.
     */
    private function prepareBindingNetwork(
        Client $client,
        Router $router,
        ClientRouterBinding $binding
    ): bool {
        $zoneId =
            (int) (
                $router->zone_id
                ?? 0
            );

        if ($zoneId <= 0) {
            return $this
                ->failBindingNetwork(
                    $binding,
                    'Target router has no Network Zone.'
                );
        }

        /*
         * Existing valid binding keeps its IP.
         */
        if (
            (int) (
                $binding->zone_id
                ?? 0
            ) === $zoneId
            && $binding->ip_range_id
            && $binding->ip_address
        ) {
            return true;
        }

        /*
         * Home billing zone keeps the original
         * client IP and IP Pool.
         */
        if (
            (int) $client->zone_id
            === $zoneId
        ) {
            if (
                !$client->ip_range_id
                || !$client->ip_address
            ) {
                return $this
                    ->failBindingNetwork(
                        $binding,
                        'Home-zone client IP/IP Pool is missing.'
                    );
            }

            $binding->forceFill([
                'zone_id' =>
                    $zoneId,

                'ip_range_id' =>
                    $client->ip_range_id,

                'ip_address' =>
                    $client->ip_address,
            ])->save();

            return true;
        }

        /*
         * Another router in the same target zone
         * may already own this client's zone-local IP.
         */
        $sibling =
            ClientRouterBinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'zone_id',
                    $zoneId
                )
                ->where(
                    'id',
                    '!=',
                    $binding->id
                )
                ->whereNotNull(
                    'ip_range_id'
                )
                ->whereNotNull(
                    'ip_address'
                )
                ->where(
                    'sync_status',
                    '!=',
                    'removed'
                )
                ->orderBy('id')
                ->first();

        if ($sibling) {
            $binding->forceFill([
                'zone_id' =>
                    $zoneId,

                'ip_range_id' =>
                    $sibling
                        ->ip_range_id,

                'ip_address' =>
                    $sibling
                        ->ip_address,
            ])->save();

            return true;
        }

        /*
         * First router encountered for this target
         * zone: reserve a fresh IP from that zone.
         */
        $allocation =
            $this
                ->ipAllocatorService
                ->allocateForResellerZone(
                    $client->reseller_id
                        === null
                            ? null
                            : (int)
                                $client
                                    ->reseller_id,
                    $zoneId
                );

        if (!$allocation) {
            return $this
                ->failBindingNetwork(
                    $binding,
                    'No enabled IP Pool/free IP is available in target zone.'
                );
        }

        $binding->forceFill([
            'zone_id' =>
                $zoneId,

            'ip_range_id' =>
                $allocation[
                    'range'
                ]->id,

            'ip_address' =>
                $allocation[
                    'ip'
                ],
        ])->save();

        return true;
    }

    private function failBindingNetwork(
        ClientRouterBinding $binding,
        string $message
    ): bool {
        $binding->forceFill([
            'sync_status' =>
                'failed',

            'last_synced_at' =>
                now(),

            'last_error' =>
                $message,
        ])->save();

        Log::warning(
            'Client roaming network preparation failed.',
            [
                'client_id' =>
                    $binding->client_id,

                'router_id' =>
                    $binding->router_id,

                'message' =>
                    $message,
            ]
        );

        return false;
    }

    private function syncLegacyPrimaryIds(
        Client $client
    ): void {
        if (!$client->router_id) {
            return;
        }

        $binding =
            ClientRouterBinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'router_id',
                    $client->router_id
                )
                ->first();

        if (!$binding) {
            return;
        }

        $client->forceFill([
            'mikrotik_lease_id' =>
                $binding
                    ->mikrotik_lease_id,

            'mikrotik_arp_id' =>
                $binding
                    ->mikrotik_arp_id,

            'mikrotik_queue_id' =>
                $binding
                    ->mikrotik_queue_id,
        ])->saveQuietly();
    }
}
