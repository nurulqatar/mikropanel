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
            ->with('router')
            ->get();

        foreach ($bindings as $binding) {
            $router = $binding->router;

            if (!$router) {
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
    public function syncClientToRouter(
        Client $client,
        Router $router
    ): bool {
        $client->loadMissing([
            'package',
            'ipRange',
        ]);

        /*
         * ZONE_ROUTER_BOUNDARY_V5
         *
         * MAC client state can only be written
         * to a MikroTik in the same zone.
         */
        if (
            !$client->zone_id
            || !$router->zone_id
        ) {
            Log::error(
                'Zone-bound MikroTik sync rejected because zone information is missing.',
                [
                    'client_id' =>
                        $client->id,

                    'client_zone_id' =>
                        $client->zone_id,

                    'router_id' =>
                        $router->id,

                    'router_zone_id' =>
                        $router->zone_id,
                ]
            );

            return false;
        }

        if (
            (int) $client->zone_id
            !== (int) $router->zone_id
        ) {
            return true;
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

        $ok = $this->syncOneRouter(
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
        /*
         * ZONE_FANOUT_QUERY_V5
         *
         * Same MAC/IP/state goes to every enabled
         * MikroTik inside this client's zone.
         */
        /*
         * TRANSFER_AUTH_INDEPENDENT_FANOUT_V1
         *
         * Network convergence follows the CLIENT'S
         * explicit zone, not the currently logged-in
         * operator's ZoneScope.
         *
         * This is required when a destination operator
         * completes a transfer or when source state
         * must be restored after a failed move.
         */
        $routers =
            Router::withoutGlobalScopes()
                ->where(
                    'enabled',
                    true
                )
                ->where(
                    'zone_id',
                    $client->zone_id
                )
                ->orderBy('id')
                ->get();

        $result = [
            'synced' => 0,
            'failed' => 0,
        ];

        foreach ($routers as $router) {
            if (
                $this->syncOneRouter(
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
                'Zone client sync found no enabled routers.',
                [
                    'client_id' =>
                        $client->id,
                ]
            );
        }

        return $result;
    }

    /*
     * Converge one router to the desired
     * panel state.
     *
     * Same client_code, MAC and GLOBAL IP
     * are used on every MikroTik.
     */
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

        $context->forceFill([
            /*
             * Runtime router only.
             * This clone is NEVER saved.
             */
            'router_id' =>
                $router->id,

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

        return $context;
    }

    /*
     * Keep the old single-router columns
     * populated for existing monitoring code.
     *
     * They represent the client's historical
     * primary router only. Multi-router truth
     * lives in client_router_bindings.
     */
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
