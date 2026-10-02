<?php

namespace App\Services\Rental;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class RentalMasterService
{
    public function syncAll(): array
    {
        return [
            'company' => $this->syncCompanies(),
            'hotel' => $this->syncHotels(),
            'compliance' => $this->syncCompliance(),
        ];
    }

    public function maintain(): array
    {
        $synced = $this->syncAll();
        $statusChanges = 0;
        $invoices = 0;
        $notifications = 0;

        foreach (DB::table('rental_contracts')->orderBy('id')->get() as $contract) {
            $statusChanges += $this->refreshLifecycle($contract);
            $fresh = DB::table('rental_contracts')->find($contract->id);
            if (!$fresh) {
                continue;
            }
            $notifications += $this->createLifecycleNotifications($fresh);
            $invoices += $this->autoInvoice($fresh);
        }

        return [
            'synced' => $synced,
            'status_changes' => $statusChanges,
            'invoices_created' => $invoices,
            'notifications_created' => $notifications,
        ];
    }

    public function updateContract(int $contractId, array $data, ?int $actorId): void
    {
        DB::transaction(function () use ($contractId, $data, $actorId): void {
            $contract = DB::table('rental_contracts')->lockForUpdate()->find($contractId);
            if (!$contract) {
                throw new RuntimeException('Rental contract not found.');
            }

            $old = [
                'agreed_price' => $contract->agreed_price,
                'billing_days' => $contract->billing_days,
                'grace_days' => $contract->grace_days,
                'deployment_status' => $contract->deployment_status,
                'trial_ends_at' => $contract->trial_ends_at,
                'retention_until' => $contract->retention_until,
                'auto_invoice' => $contract->auto_invoice,
                'auto_notify' => $contract->auto_notify,
                'notes' => $contract->notes,
            ];

            $payload = [
                'agreed_price' => round((float) $data['agreed_price'], 2),
                'billing_days' => max(1, (int) $data['billing_days']),
                'grace_days' => max(0, (int) $data['grace_days']),
                'deployment_status' => $data['deployment_status'],
                'trial_ends_at' => $this->nullableDateTime($data['trial_ends_at'] ?? null),
                'retention_until' => $this->nullableDateTime($data['retention_until'] ?? null),
                'auto_invoice' => (bool) $data['auto_invoice'],
                'auto_notify' => (bool) $data['auto_notify'],
                'notes' => $data['notes'] ?? null,
                'updated_at' => now(),
            ];

            if ($contract->expires_at) {
                $payload['grace_until'] = CarbonImmutable::parse($contract->expires_at)
                    ->addDays($payload['grace_days']);
            }

            DB::table('rental_contracts')->where('id', $contractId)->update($payload);
            $this->event($contractId, 'contract.updated', $old, $payload, $actorId, 'Commercial rental settings updated.');
        });
    }

    public function cancelContract(
        int $contractId,
        ?string $retentionUntil,
        ?string $notes,
        ?int $actorId
    ): void {
        DB::transaction(function () use ($contractId, $retentionUntil, $notes, $actorId): void {
            $contract = DB::table('rental_contracts')->lockForUpdate()->find($contractId);
            if (!$contract) {
                throw new RuntimeException('Rental contract not found.');
            }
            if ($contract->status === 'cancelled') {
                return;
            }

            $payload = [
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'retention_until' => $this->nullableDateTime($retentionUntil),
                'notes' => $notes ?: $contract->notes,
                'updated_at' => now(),
            ];

            DB::table('rental_contracts')->where('id', $contractId)->update($payload);
            $this->event(
                $contractId,
                'contract.cancelled',
                ['status' => $contract->status],
                $payload,
                $actorId,
                'Commercial rental cancelled. Source service and retained records were not deleted.'
            );
        });
    }

    public function createInvoice(
        int $contractId,
        array $data,
        ?int $actorId,
        ?string $dedupeKey = null
    ): int {
        return DB::transaction(function () use ($contractId, $data, $actorId, $dedupeKey): int {
            $contract = DB::table('rental_contracts')->lockForUpdate()->find($contractId);
            if (!$contract) {
                throw new RuntimeException('Rental contract not found.');
            }

            if ($dedupeKey) {
                $existing = DB::table('rental_invoices')->where('dedupe_key', $dedupeKey)->first();
                if ($existing) {
                    return (int) $existing->id;
                }
            }

            $amount = max(0, round((float) $data['amount'], 2));
            $discount = max(0, min($amount, round((float) ($data['discount'] ?? 0), 2)));
            $due = round($amount - $discount, 2);
            $invoiceNo = 'RNT-' . now()->format('YmdHis') . '-' . $contractId . '-' . strtoupper(Str::random(4));

            $id = DB::table('rental_invoices')->insertGetId([
                'rental_contract_id' => $contractId,
                'invoice_no' => $invoiceNo,
                'dedupe_key' => $dedupeKey,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'amount' => $amount,
                'discount' => $discount,
                'paid_amount' => 0,
                'due_amount' => $due,
                'status' => $due > 0 ? 'unpaid' : 'paid',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->event($contractId, 'invoice.created', null, [
                'invoice_id' => $id,
                'invoice_no' => $invoiceNo,
                'amount' => $amount,
                'discount' => $discount,
                'due_amount' => $due,
            ], $actorId, 'Rental invoice created.');

            return (int) $id;
        });
    }

    public function recordPayment(int $invoiceId, array $data, ?int $actorId): int
    {
        return DB::transaction(function () use ($invoiceId, $data, $actorId): int {
            $invoice = DB::table('rental_invoices')->lockForUpdate()->find($invoiceId);
            if (!$invoice) {
                throw new RuntimeException('Rental invoice not found.');
            }
            if ($invoice->status === 'cancelled') {
                throw new RuntimeException('Cancelled invoice cannot receive payment.');
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount > (float) $invoice->due_amount + 0.0001) {
                throw new RuntimeException('Payment must be greater than zero and cannot exceed current due.');
            }

            $paymentId = DB::table('rental_payments')->insertGetId([
                'rental_contract_id' => $invoice->rental_contract_id,
                'rental_invoice_id' => $invoice->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? 'Cash',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paid = round((float) $invoice->paid_amount + $amount, 2);
            $due = max(0, round((float) $invoice->due_amount - $amount, 2));
            $status = $due <= 0 ? 'paid' : 'partial';

            DB::table('rental_invoices')->where('id', $invoiceId)->update([
                'paid_amount' => $paid,
                'due_amount' => $due,
                'status' => $status,
                'updated_at' => now(),
            ]);

            $this->event(
                (int) $invoice->rental_contract_id,
                'payment.received',
                ['invoice_due' => $invoice->due_amount],
                ['payment_id' => $paymentId, 'amount' => $amount, 'invoice_due' => $due, 'invoice_status' => $status],
                $actorId,
                'Manual rental payment recorded.'
            );

            return (int) $paymentId;
        });
    }

    public function createTicket(int $contractId, array $data, ?int $actorId): int
    {
        if (!DB::table('rental_contracts')->where('id', $contractId)->exists()) {
            throw new RuntimeException('Rental contract not found.');
        }

        $id = DB::table('rental_tickets')->insertGetId([
            'rental_contract_id' => $contractId,
            'category' => $data['category'],
            'priority' => $data['priority'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->event($contractId, 'ticket.created', null, ['ticket_id' => $id, 'subject' => $data['subject']], $actorId, 'Support ticket created.');
        return (int) $id;
    }

    public function updateTicket(int $ticketId, array $data, ?int $actorId): void
    {
        DB::transaction(function () use ($ticketId, $data, $actorId): void {
            $ticket = DB::table('rental_tickets')->lockForUpdate()->find($ticketId);
            if (!$ticket) {
                throw new RuntimeException('Rental ticket not found.');
            }

            $payload = [
                'status' => $data['status'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'resolution' => $data['resolution'] ?? null,
                'closed_at' => $data['status'] === 'closed' ? now() : null,
                'updated_at' => now(),
            ];

            DB::table('rental_tickets')->where('id', $ticketId)->update($payload);
            $this->event((int) $ticket->rental_contract_id, 'ticket.updated', ['status' => $ticket->status], $payload, $actorId, 'Support ticket updated.');
        });
    }

    public function nativeUrl(object $contract): string
    {
        return match ($contract->service_type) {
            'company' => '/super-admin/resellers/' . $contract->source_id,
            'hotel' => '/super-admin/hotel-hotspot/hotels/' . $contract->source_id . '/edit',
            'compliance' => '/super-admin/compliance#compliance-rental-customers',
            default => '/super-admin',
        };
    }

    private function syncCompanies(): int
    {
        if (!Schema::hasTable('resellers')) {
            return 0;
        }

        $query = DB::table('resellers');
        if (Schema::hasColumn('resellers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $count = 0;
        foreach ($query->orderBy('id')->get() as $row) {
            $subscription = Schema::hasTable('reseller_subscriptions')
                ? DB::table('reseller_subscriptions')
                    ->where('reseller_id', $row->id)
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderByDesc('id')
                    ->first()
                : null;

            $plan = ($subscription?->reseller_plan_id && Schema::hasTable('reseller_plans'))
                ? DB::table('reseller_plans')->find($subscription->reseller_plan_id)
                : null;

            $price = (float) ($subscription?->price ?? $plan?->price ?? 0);
            $billingDays = (int) ($plan?->validity_days
                ?? $this->daysBetween($subscription?->starts_at, $subscription?->expires_at)
                ?? 30);

            $this->upsertContract([
                'service_type' => 'company',
                'source_id' => (int) $row->id,
                'source_subscription_id' => $subscription?->id,
                'source_plan_id' => $subscription?->reseller_plan_id,
                'linked_service_type' => null,
                'linked_source_id' => null,
                'customer_name' => $row->company_name ?? ('Company #' . $row->id),
                'customer_email' => $row->email ?? null,
                'currency' => $row->currency ?? 'QAR',
                'plan_name' => $plan?->name,
                'standard_price' => $price,
                'initial_agreed_price' => $price,
                'billing_days' => max(1, $billingDays),
                'starts_at' => $subscription?->starts_at,
                'expires_at' => $subscription?->expires_at,
                'source_grace_until' => $subscription?->grace_until,
                'entity_status' => $row->status ?? null,
                'subscription_status' => $subscription?->status,
                'deployment_status' => 'live',
            ]);
            $count++;
        }

        return $count;
    }

    private function syncHotels(): int
    {
        if (!Schema::hasTable('hotels')) {
            return 0;
        }

        $count = 0;
        foreach (DB::table('hotels')->orderBy('id')->get() as $row) {
            $subscription = Schema::hasTable('hotel_subscriptions')
                ? DB::table('hotel_subscriptions')
                    ->where('hotel_id', $row->id)
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderByDesc('id')
                    ->first()
                : null;

            $planId = $this->firstValue($subscription, ['hotel_plan_id', 'plan_id']);
            $plan = ($planId && Schema::hasTable('hotel_plans'))
                ? DB::table('hotel_plans')->find($planId)
                : null;

            $price = (float) (
                $this->firstValue($subscription, ['price', 'monthly_price', 'amount'])
                ?? $this->firstValue($plan, ['price', 'monthly_price', 'amount'])
                ?? 0
            );

            $starts = $this->firstValue($subscription, ['starts_at', 'start_at']);
            $expires = $this->firstValue($subscription, ['expires_at', 'expiry_at']);
            $billingDays = (int) (
                $this->firstValue($plan, ['validity_days', 'billing_days'])
                ?? $this->daysBetween($starts, $expires)
                ?? 30
            );

            $routers = Schema::hasTable('hotel_routers')
                ? DB::table('hotel_routers')->where('hotel_id', $row->id)->count()
                : 0;

            $this->upsertContract([
                'service_type' => 'hotel',
                'source_id' => (int) $row->id,
                'source_subscription_id' => $subscription?->id,
                'source_plan_id' => $planId,
                'linked_service_type' => null,
                'linked_source_id' => null,
                'customer_name' => $row->name ?? ('Hotel #' . $row->id),
                'customer_email' => $row->email ?? null,
                'currency' => $row->currency ?? 'QAR',
                'plan_name' => $this->firstValue($plan, ['name', 'title']),
                'standard_price' => $price,
                'initial_agreed_price' => $price,
                'billing_days' => max(1, $billingDays),
                'starts_at' => $starts,
                'expires_at' => $expires,
                'source_grace_until' => $this->firstValue($subscription, ['grace_until']),
                'entity_status' => $row->status ?? null,
                'subscription_status' => $this->firstValue($subscription, ['status']),
                'deployment_status' => $routers > 0 ? 'live' : 'pending_hardware',
            ]);
            $count++;
        }

        return $count;
    }

    private function syncCompliance(): int
    {
        if (!Schema::hasTable('compliance_rentals')) {
            return 0;
        }

        $count = 0;
        foreach (DB::table('compliance_rentals')->orderBy('id')->get() as $row) {
            $plan = Schema::hasTable('compliance_plans')
                ? DB::table('compliance_plans')->find($row->plan_id)
                : null;
            $subscription = ($row->subscription_id && Schema::hasTable('compliance_subscriptions'))
                ? DB::table('compliance_subscriptions')->find($row->subscription_id)
                : null;
            $organization = Schema::hasTable('compliance_organizations')
                ? DB::table('compliance_organizations')->find($row->organization_id)
                : null;

            $this->upsertContract([
                'service_type' => 'compliance',
                'source_id' => (int) $row->id,
                'source_subscription_id' => $row->subscription_id,
                'source_plan_id' => $row->plan_id,
                'linked_service_type' => $row->source_type ?? null,
                'linked_source_id' => $row->source_id ?? null,
                'customer_name' => $row->customer_name ?? $organization?->name ?? ('Compliance #' . $row->id),
                'customer_email' => $organization?->contact_email,
                'currency' => $row->currency ?? 'QAR',
                'plan_name' => $plan?->name,
                'standard_price' => (float) ($plan?->monthly_price ?? $row->amount ?? 0),
                'initial_agreed_price' => (float) ($row->amount ?? 0),
                'billing_days' => max(1, $this->daysBetween($row->starts_at, $row->expires_at) ?? 30),
                'starts_at' => $row->starts_at,
                'expires_at' => $row->expires_at,
                'source_grace_until' => null,
                'entity_status' => $row->status,
                'subscription_status' => $subscription?->status,
                'deployment_status' => $row->deployment_status ?? 'pending_hardware',
            ]);
            $count++;
        }

        return $count;
    }

    private function upsertContract(array $source): void
    {
        $existing = DB::table('rental_contracts')
            ->where('service_type', $source['service_type'])
            ->where('source_id', $source['source_id'])
            ->first();

        $graceDays = (int) ($existing?->grace_days ?? 3);
        $computedGrace = $this->nullableDateTime($source['source_grace_until'] ?? null);
        if (!$computedGrace && ($source['expires_at'] ?? null)) {
            $computedGrace = CarbonImmutable::parse($source['expires_at'])->addDays($graceDays);
        }

        $status = $this->derivedStatus(
            $existing,
            $source['entity_status'] ?? null,
            $source['subscription_status'] ?? null,
            $source['starts_at'] ?? null,
            $source['expires_at'] ?? null,
            $computedGrace
        );

        $payload = [
            'source_subscription_id' => $source['source_subscription_id'] ?? null,
            'source_plan_id' => $source['source_plan_id'] ?? null,
            'linked_service_type' => $source['linked_service_type'] ?? null,
            'linked_source_id' => $source['linked_source_id'] ?? null,
            'customer_name' => $source['customer_name'],
            'customer_email' => $source['customer_email'] ?? null,
            'currency' => $source['currency'] ?? 'QAR',
            'plan_name' => $source['plan_name'] ?? null,
            'standard_price' => round((float) ($source['standard_price'] ?? 0), 2),
            'billing_days' => max(1, (int) ($source['billing_days'] ?? 30)),
            'starts_at' => $this->nullableDateTime($source['starts_at'] ?? null),
            'expires_at' => $this->nullableDateTime($source['expires_at'] ?? null),
            'grace_until' => $computedGrace,
            'status' => $status,
            'deployment_status' => $source['deployment_status'] ?? ($existing?->deployment_status ?? 'live'),
            'last_synced_at' => now(),
            'updated_at' => now(),
        ];

        if ($existing) {
            $before = [
                'source_plan_id' => $existing->source_plan_id,
                'plan_name' => $existing->plan_name,
                'expires_at' => $existing->expires_at,
                'status' => $existing->status,
            ];

            DB::table('rental_contracts')->where('id', $existing->id)->update($payload);

            $after = [
                'source_plan_id' => $payload['source_plan_id'],
                'plan_name' => $payload['plan_name'],
                'expires_at' => $payload['expires_at']?->toDateTimeString(),
                'status' => $payload['status'],
            ];

            if (json_encode($before) !== json_encode($after)) {
                $this->event((int) $existing->id, 'source.lifecycle_changed', $before, $after, null, 'Native rental plan, expiry or status changed and was synchronized.');
            }
            return;
        }

        DB::table('rental_contracts')->insert([
            'service_type' => $source['service_type'],
            'source_id' => $source['source_id'],
            ...$payload,
            'agreed_price' => round((float) ($source['initial_agreed_price'] ?? $source['standard_price'] ?? 0), 2),
            'grace_days' => $graceDays,
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'retention_until' => null,
            'auto_invoice' => true,
            'auto_notify' => true,
            'portal_token' => hash('sha256', Str::uuid() . '|' . Str::random(64)),
            'notes' => null,
            'created_by' => null,
            'created_at' => now(),
        ]);
    }

    private function refreshLifecycle(object $contract): int
    {
        if ($contract->status === 'cancelled') {
            return 0;
        }

        $status = $this->derivedStatus(
            $contract,
            null,
            null,
            $contract->starts_at,
            $contract->expires_at,
            $contract->grace_until
        );

        if ($status === $contract->status) {
            return 0;
        }

        DB::table('rental_contracts')->where('id', $contract->id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
        $this->event((int) $contract->id, 'status.changed', ['status' => $contract->status], ['status' => $status], null, 'Automatic commercial rental lifecycle update.');
        return 1;
    }

    private function createLifecycleNotifications(object $contract): int
    {
        if (!$contract->auto_notify || !$contract->expires_at || $contract->status === 'cancelled') {
            return 0;
        }

        $now = CarbonImmutable::now('Asia/Qatar')->startOfDay();
        $expiry = CarbonImmutable::parse($contract->expires_at, 'Asia/Qatar')->startOfDay();
        $days = (int) $now->diffInDays($expiry, false);
        $messages = [];

        if (in_array($days, [7, 3, 1, 0], true)) {
            $messages[] = [
                'type' => 'expiry',
                'level' => $days <= 1 ? 'warning' : 'info',
                'title' => $days === 0 ? 'Rental Expires Today' : 'Rental Expires in ' . $days . ' Day' . ($days === 1 ? '' : 's'),
                'message' => $contract->customer_name . ' - ' . $contract->service_type . ' rental expires on ' . $expiry->toDateString() . '.',
                'dedupe' => 'expiry:' . $contract->id . ':' . $expiry->toDateString() . ':' . $days,
            ];
        }

        if ($days < 0) {
            $messages[] = [
                'type' => 'expired',
                'level' => 'danger',
                'title' => 'Rental Expired',
                'message' => $contract->customer_name . ' - ' . $contract->service_type . ' rental has expired.',
                'dedupe' => 'expired:' . $contract->id . ':' . $expiry->toDateString(),
            ];
        }

        $created = 0;
        foreach ($messages as $message) {
            if (DB::table('rental_notifications')->where('dedupe_key', $message['dedupe'])->exists()) {
                continue;
            }

            DB::table('rental_notifications')->insert([
                'rental_contract_id' => $contract->id,
                'type' => $message['type'],
                'level' => $message['level'],
                'title' => $message['title'],
                'message' => $message['message'],
                'dedupe_key' => $message['dedupe'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    private function autoInvoice(object $contract): int
    {
        if (!$contract->auto_invoice || !$contract->expires_at || $contract->status === 'cancelled' || (float) $contract->agreed_price <= 0) {
            return 0;
        }

        $now = CarbonImmutable::now('Asia/Qatar')->startOfDay();
        $expiry = CarbonImmutable::parse($contract->expires_at, 'Asia/Qatar')->startOfDay();
        $days = (int) $now->diffInDays($expiry, false);

        if ($days > 7 || $days < -1 * max(0, (int) $contract->grace_days)) {
            return 0;
        }

        $dedupe = 'renewal:' . $contract->id . ':' . $expiry->toDateString();
        if (DB::table('rental_invoices')->where('dedupe_key', $dedupe)->exists()) {
            return 0;
        }

        $this->createInvoice((int) $contract->id, [
            'period_start' => $expiry->toDateString(),
            'period_end' => $expiry->addDays(max(1, (int) $contract->billing_days))->toDateString(),
            'amount' => (float) $contract->agreed_price,
            'discount' => 0,
            'issue_date' => now()->toDateString(),
            'due_date' => $expiry->toDateString(),
            'notes' => 'Automatically generated upcoming rental renewal invoice. Payment does not automatically change native service expiry.',
        ], null, $dedupe);

        return 1;
    }

    private function derivedStatus(
        ?object $existing,
        ?string $entityStatus,
        ?string $subscriptionStatus,
        mixed $startsAt,
        mixed $expiresAt,
        mixed $graceUntil
    ): string {
        if ($existing && $existing->status === 'cancelled') {
            return 'cancelled';
        }

        $entity = strtolower((string) $entityStatus);
        $subscription = strtolower((string) $subscriptionStatus);

        if (in_array($entity, ['suspended', 'disabled', 'blocked'], true) || $subscription === 'suspended') {
            return 'suspended';
        }

        if ($subscription === 'replaced') {
            return 'replaced';
        }

        if ($existing?->trial_ends_at && CarbonImmutable::parse($existing->trial_ends_at)->isFuture()) {
            return 'trial';
        }

        if ($startsAt && CarbonImmutable::parse($startsAt)->isFuture()) {
            return 'pending';
        }

        if ($expiresAt) {
            $expiry = CarbonImmutable::parse($expiresAt);
            if ($expiry->isFuture()) {
                return 'active';
            }
            if ($graceUntil && CarbonImmutable::parse($graceUntil)->isFuture()) {
                return 'grace';
            }
            return 'expired';
        }

        if (in_array($entity, ['pending', 'inactive'], true)) {
            return $entity;
        }
        if ($subscription === 'cancelled') {
            return 'cancelled';
        }
        if ($subscription === 'expired') {
            return 'expired';
        }

        return 'active';
    }

    private function event(
        int $contractId,
        string $type,
        ?array $old,
        ?array $new,
        ?int $actorId,
        ?string $notes
    ): void {
        DB::table('rental_events')->insert([
            'rental_contract_id' => $contractId,
            'event_type' => $type,
            'old_value' => $old ? json_encode($old, JSON_UNESCAPED_SLASHES) : null,
            'new_value' => $new ? json_encode($new, JSON_UNESCAPED_SLASHES) : null,
            'notes' => $notes,
            'actor_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function firstValue(?object $row, array $columns): mixed
    {
        if (!$row) {
            return null;
        }
        foreach ($columns as $column) {
            if (property_exists($row, $column) && $row->{$column} !== null) {
                return $row->{$column};
            }
        }
        return null;
    }

    private function daysBetween(mixed $start, mixed $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }
        try {
            return max(1, (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)));
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableDateTime(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return CarbonImmutable::parse($value);
    }
}
