<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'compliance_plans',
            function (Blueprint $table): void {
                $table->id();

                $table->string('name');
                $table->string('code')->unique();

                /*
                 * logging
                 * filtering
                 * bundle
                 */
                $table->string(
                    'service_type',
                    20
                );

                $table->decimal(
                    'monthly_price',
                    12,
                    2
                )->default(0);

                $table->boolean(
                    'call_for_price'
                )->default(false);

                $table->unsignedInteger(
                    'site_limit'
                )->nullable();

                $table->unsignedInteger(
                    'router_limit'
                )->nullable();

                $table->unsignedInteger(
                    'collector_limit'
                )->nullable();

                $table->unsignedInteger(
                    'staff_limit'
                )->nullable();

                $table->unsignedInteger(
                    'retention_days'
                )->nullable();

                $table->unsignedInteger(
                    'log_volume_gb'
                )->nullable();

                $table->unsignedInteger(
                    'filter_rule_limit'
                )->nullable();

                $table->boolean('enabled')
                    ->default(true);

                $table->text('description')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'service_type',
                    'enabled',
                ]);
            }
        );

        Schema::create(
            'compliance_organizations',
            function (Blueprint $table): void {
                $table->id();

                $table->string('name');
                $table->string('code')->unique();

                /*
                 * standalone
                 * reseller
                 * hotel
                 * mixed
                 */
                $table->string(
                    'account_type',
                    30
                )->default('standalone');

                /*
                 * private_enterprise
                 * service_provider
                 * government_affiliated
                 * custom
                 */
                $table->string(
                    'legal_profile',
                    40
                )->default(
                    'private_enterprise'
                );

                $table->string(
                    'country_code',
                    2
                )->default('QA');

                $table->string('timezone')
                    ->default('Asia/Qatar');

                $table->string(
                    'contact_name'
                )->nullable();

                $table->string(
                    'contact_email'
                )->nullable();

                $table->string(
                    'contact_phone'
                )->nullable();

                $table->string(
                    'status',
                    20
                )->default('active');

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'account_type',
                    'status',
                ]);
            }
        );

        Schema::create(
            'compliance_users',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string('name');

                $table->string('email')
                    ->unique();

                $table->string('password');

                /*
                 * owner
                 * admin
                 * network_admin
                 * compliance_officer
                 * investigator
                 * auditor
                 * read_only
                 */
                $table->string(
                    'role',
                    30
                )->default('owner');

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->timestamp(
                    'last_login_at'
                )->nullable();

                $table->rememberToken();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'is_active',
                ]);
            }
        );

        Schema::create(
            'compliance_subscriptions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'plan_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_plans'
                    )
                    ->nullOnDelete();

                $table->boolean(
                    'logging_enabled'
                )->default(false);

                $table->boolean(
                    'filtering_enabled'
                )->default(false);

                $table->string(
                    'billing_cycle',
                    20
                )->default('monthly');

                $table->decimal(
                    'custom_monthly_price',
                    12,
                    2
                )->nullable();

                $table->string(
                    'status',
                    20
                )->default('active');

                $table->timestamp(
                    'starts_at'
                )->nullable();

                $table->timestamp(
                    'expires_at'
                )->nullable();

                /*
                 * Never silently remove the
                 * last deployed filtering
                 * policy on subscription expiry.
                 */
                $table->boolean(
                    'keep_last_filter_policy_on_expiry'
                )->default(true);

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'status',
                    'expires_at',
                ]);
            }
        );

        /*
         * Optional link to existing
         * MAC/Hotspot reseller or Hotel.
         */
        Schema::create(
            'compliance_access_grants',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'source_type',
                    30
                );

                $table->unsignedBigInteger(
                    'source_id'
                );

                $table->boolean(
                    'logging_enabled'
                )->default(false);

                $table->boolean(
                    'filtering_enabled'
                )->default(false);

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->timestamps();

                $table->unique(
                    [
                        'source_type',
                        'source_id',
                        'organization_id',
                    ],
                    'compliance_grant_unique'
                );

                $table->index([
                    'source_type',
                    'source_id',
                    'is_active',
                ]);
            }
        );

        Schema::create(
            'compliance_networks',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string('name');

                $table->string(
                    'site_code'
                )->nullable();

                $table->json(
                    'local_networks'
                )->nullable();

                /*
                 * blocklist:
                 * everything allowed except blocks.
                 *
                 * allowlist:
                 * everything denied except allows.
                 */
                $table->string(
                    'filter_mode',
                    20
                )->default('blocklist');

                $table->boolean('enabled')
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'enabled',
                ]);
            }
        );

        Schema::create(
            'compliance_routers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'network_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_networks'
                    )
                    ->nullOnDelete();

                $table->string('name');

                /*
                 * mikrotik
                 * openwrt
                 * pfsense
                 * opnsense
                 * linux
                 * other
                 */
                $table->string(
                    'vendor',
                    30
                );

                $table->string('host');

                $table->unsignedInteger(
                    'management_port'
                )->nullable();

                $table->string(
                    'management_protocol',
                    20
                )->default('api');

                $table->string(
                    'api_username'
                )->nullable();

                /*
                 * Encrypted using Laravel Crypt.
                 */
                $table->text(
                    'credential_encrypted'
                )->nullable();

                $table->string(
                    'wan_ip',
                    45
                )->nullable();

                $table->string(
                    'observed_public_ip',
                    45
                )->nullable();

                $table->boolean(
                    'cgnat_detected'
                )->nullable();

                $table->json(
                    'capabilities'
                )->nullable();

                $table->string(
                    'connection_status',
                    20
                )->default('pending');

                $table->timestamp(
                    'last_seen_at'
                )->nullable();

                $table->boolean('enabled')
                    ->default(true);

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'vendor',
                    'enabled',
                ]);
            }
        );

        Schema::create(
            'compliance_collectors',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'network_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_networks'
                    )
                    ->nullOnDelete();

                $table->uuid('uuid')
                    ->unique();

                $table->string('name');

                $table->string(
                    'token_hash'
                )->nullable();

                $table->string(
                    'version'
                )->nullable();

                $table->string(
                    'status',
                    20
                )->default('pending');

                $table->json(
                    'capabilities'
                )->nullable();

                $table->timestamp(
                    'last_seen_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'status',
                ]);
            }
        );

        Schema::create(
            'compliance_storage_targets',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string('name');

                /*
                 * local
                 * nfs
                 * sftp
                 * s3
                 * s3_compatible
                 */
                $table->string(
                    'driver',
                    30
                );

                $table->longText(
                    'config_encrypted'
                )->nullable();

                $table->string(
                    'health_status',
                    20
                )->default('pending');

                $table->timestamp(
                    'last_health_check_at'
                )->nullable();

                $table->boolean('enabled')
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'enabled',
                ]);
            }
        );

        Schema::create(
            'compliance_retention_policies',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string('name');

                $table->unsignedInteger(
                    'retention_days'
                );

                $table->boolean(
                    'automatic_purge'
                )->default(true);

                $table->boolean(
                    'legal_hold_override'
                )->default(true);

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'is_active',
                ]);
            }
        );

        Schema::create(
            'compliance_identity_bindings',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'router_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_routers'
                    )
                    ->nullOnDelete();

                $table->string(
                    'identity_type',
                    30
                )->default('device');

                $table->string(
                    'identity_key'
                )->nullable();

                $table->string(
                    'display_name'
                )->nullable();

                $table->string(
                    'mac_address',
                    17
                )->nullable();

                $table->string(
                    'private_ip',
                    45
                )->nullable();

                $table->timestamp(
                    'started_at'
                );

                $table->timestamp(
                    'ended_at'
                )->nullable();

                $table->string(
                    'source',
                    30
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'organization_id',
                        'mac_address',
                        'started_at',
                    ],
                    'cmp_identity_mac_time_idx'
                );

                $table->index(
                    [
                        'organization_id',
                        'private_ip',
                        'started_at',
                    ],
                    'cmp_identity_ip_time_idx'
                );
            }
        );

        /*
         * Public IP/Port to internal
         * client correlation.
         */
        Schema::create(
            'compliance_nat_mappings',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'router_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_routers'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'collector_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_collectors'
                    )
                    ->nullOnDelete();

                $table->string(
                    'protocol',
                    10
                );

                $table->string(
                    'public_ip',
                    45
                );

                $table->unsignedInteger(
                    'public_port'
                );

                $table->string(
                    'private_ip',
                    45
                );

                $table->unsignedInteger(
                    'private_port'
                )->nullable();

                $table->string(
                    'destination_ip',
                    45
                )->nullable();

                $table->unsignedInteger(
                    'destination_port'
                )->nullable();

                $table->string(
                    'mac_address',
                    17
                )->nullable();

                $table->string(
                    'identity_key'
                )->nullable();

                $table->timestamp(
                    'started_at'
                );

                $table->timestamp(
                    'ended_at'
                )->nullable();

                $table->string(
                    'archive_ref'
                )->nullable();

                $table->string(
                    'integrity_hash',
                    64
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'organization_id',
                        'public_ip',
                        'public_port',
                        'started_at',
                    ],
                    'cmp_nat_public_lookup_idx'
                );

                $table->index(
                    [
                        'organization_id',
                        'private_ip',
                        'started_at',
                    ],
                    'cmp_nat_private_lookup_idx'
                );
            }
        );

        Schema::create(
            'compliance_filter_rules',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'network_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_networks'
                    )
                    ->nullOnDelete();

                $table->string('name');

                /*
                 * domain/ip/cidr/server/app/
                 * category/protocol/custom
                 */
                $table->string(
                    'rule_type',
                    20
                );

                /*
                 * allow/block
                 */
                $table->string(
                    'action',
                    10
                );

                $table->text(
                    'target_value'
                );

                $table->json('scope')
                    ->nullable();

                $table->json('schedule')
                    ->nullable();

                $table->unsignedInteger(
                    'priority'
                )->default(100);

                $table->boolean('enabled')
                    ->default(true);

                $table->string(
                    'deployment_status',
                    20
                )->default('draft');

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'enabled',
                    'priority',
                ]);
            }
        );

        Schema::create(
            'compliance_app_signatures',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('slug');

                /*
                 * global
                 * organization
                 * learned
                 */
                $table->string(
                    'scope_type',
                    20
                )->default('global');

                $table->string(
                    'category'
                )->nullable();

                $table->json('domains')
                    ->nullable();

                $table->json('ip_ranges')
                    ->nullable();

                $table->json('ports')
                    ->nullable();

                $table->json('protocols')
                    ->nullable();

                /*
                 * high
                 * medium
                 * limited
                 */
                $table->string(
                    'confidence',
                    20
                )->default('limited');

                $table->boolean('enabled')
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'slug',
                    'scope_type',
                    'enabled',
                ]);
            }
        );

        Schema::create(
            'compliance_rule_deployments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'router_id'
                )
                    ->constrained(
                        'compliance_routers'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'filter_rule_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_filter_rules'
                    )
                    ->nullOnDelete();

                $table->string(
                    'operation',
                    20
                )->default('apply');

                $table->string(
                    'status',
                    20
                )->default('pending');

                $table->string(
                    'policy_version'
                )->nullable();

                $table->string(
                    'last_good_policy_version'
                )->nullable();

                $table->text(
                    'error_message'
                )->nullable();

                $table->timestamp(
                    'applied_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'router_id',
                    'status',
                ]);
            }
        );

        /*
         * Search metadata only.
         * Full raw logs will live on
         * customer-controlled storage.
         */
        Schema::create(
            'compliance_browse_indexes',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'router_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_routers'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'collector_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_collectors'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'observed_at'
                );

                $table->string(
                    'source_ip',
                    45
                )->nullable();

                $table->string(
                    'source_mac',
                    17
                )->nullable();

                $table->string(
                    'identity_key'
                )->nullable();

                $table->string(
                    'destination_ip',
                    45
                )->nullable();

                $table->unsignedInteger(
                    'destination_port'
                )->nullable();

                $table->string(
                    'domain'
                )->nullable();

                $table->string(
                    'protocol',
                    20
                )->nullable();

                $table->unsignedBigInteger(
                    'bytes_up'
                )->default(0);

                $table->unsignedBigInteger(
                    'bytes_down'
                )->default(0);

                $table->boolean(
                    'blocked'
                )->default(false);

                $table->foreignId(
                    'filter_rule_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_filter_rules'
                    )
                    ->nullOnDelete();

                $table->string(
                    'archive_ref'
                )->nullable();

                $table->string(
                    'integrity_hash',
                    64
                )->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'observed_at',
                ]);

                $table->index(
                    [
                        'organization_id',
                        'domain',
                        'observed_at',
                    ],
                    'cmp_browse_domain_time_idx'
                );
            }
        );

        Schema::create(
            'compliance_cases',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'case_number'
                )->unique();

                $table->string('title');

                $table->string(
                    'status',
                    20
                )->default('open');

                $table->string(
                    'requesting_authority'
                )->nullable();

                $table->string(
                    'authority_reference'
                )->nullable();

                $table->text('reason')
                    ->nullable();

                $table->timestamp(
                    'opened_at'
                )->nullable();

                $table->timestamp(
                    'closed_at'
                )->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'compliance_legal_requests',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'case_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_cases'
                    )
                    ->nullOnDelete();

                $table->string(
                    'authority_name'
                )->nullable();

                $table->string(
                    'reference_number'
                )->nullable();

                $table->string(
                    'public_ip',
                    45
                )->nullable();

                $table->unsignedInteger(
                    'public_port'
                )->nullable();

                $table->string(
                    'protocol',
                    10
                )->nullable();

                $table->timestamp(
                    'requested_time'
                )->nullable();

                $table->string('timezone')
                    ->default('Asia/Qatar');

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'organization_id',
                        'public_ip',
                        'public_port',
                        'requested_time',
                    ],
                    'cmp_legal_lookup_idx'
                );
            }
        );

        Schema::create(
            'compliance_legal_holds',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'case_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_cases'
                    )
                    ->nullOnDelete();

                $table->string(
                    'scope_type',
                    30
                );

                $table->text(
                    'scope_value'
                );

                $table->timestamp(
                    'starts_at'
                );

                $table->timestamp(
                    'ends_at'
                )->nullable();

                $table->boolean('active')
                    ->default(true);

                $table->timestamps();
            }
        );

        Schema::create(
            'compliance_evidence_exports',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'case_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_cases'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'requested_by_user_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_users'
                    )
                    ->nullOnDelete();

                $table->string(
                    'format',
                    20
                )->default('zip');

                $table->string(
                    'status',
                    20
                )->default('pending');

                $table->string(
                    'archive_ref'
                )->nullable();

                $table->string(
                    'sha256',
                    64
                )->nullable();

                $table->timestamp(
                    'generated_at'
                )->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'compliance_alerts',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'severity',
                    20
                )->default('info');

                $table->string(
                    'type',
                    50
                );

                $table->string('title');

                $table->text('message');

                $table->timestamp(
                    'resolved_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'resolved_at',
                ]);
            }
        );

        Schema::create(
            'compliance_invoices',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'subscription_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_subscriptions'
                    )
                    ->nullOnDelete();

                $table->string(
                    'invoice_number'
                )->unique();

                $table->decimal(
                    'amount',
                    12,
                    2
                );

                $table->decimal(
                    'paid_amount',
                    12,
                    2
                )->default(0);

                $table->string(
                    'status',
                    20
                )->default('unpaid');

                $table->date(
                    'issue_date'
                );

                $table->date(
                    'due_date'
                )->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'compliance_payments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'invoice_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_invoices'
                    )
                    ->nullOnDelete();

                $table->decimal(
                    'amount',
                    12,
                    2
                );

                $table->string(
                    'payment_method',
                    30
                )->default('manual');

                $table->string(
                    'transaction_reference'
                )->nullable();

                $table->timestamp(
                    'paid_at'
                )->nullable();

                $table->text('notes')
                    ->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'compliance_audit_logs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_organizations'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'compliance_user_id'
                )
                    ->nullable()
                    ->constrained(
                        'compliance_users'
                    )
                    ->nullOnDelete();

                $table->unsignedBigInteger(
                    'panel_user_id'
                )->nullable();

                $table->string(
                    'action',
                    80
                );

                $table->string(
                    'subject_type',
                    80
                )->nullable();

                $table->string(
                    'subject_id'
                )->nullable();

                $table->string(
                    'ip_address',
                    45
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'compliance_audit_logs'
        );

        Schema::dropIfExists(
            'compliance_payments'
        );

        Schema::dropIfExists(
            'compliance_invoices'
        );

        Schema::dropIfExists(
            'compliance_alerts'
        );

        Schema::dropIfExists(
            'compliance_evidence_exports'
        );

        Schema::dropIfExists(
            'compliance_legal_holds'
        );

        Schema::dropIfExists(
            'compliance_legal_requests'
        );

        Schema::dropIfExists(
            'compliance_cases'
        );

        Schema::dropIfExists(
            'compliance_browse_indexes'
        );

        Schema::dropIfExists(
            'compliance_rule_deployments'
        );

        Schema::dropIfExists(
            'compliance_app_signatures'
        );

        Schema::dropIfExists(
            'compliance_filter_rules'
        );

        Schema::dropIfExists(
            'compliance_nat_mappings'
        );

        Schema::dropIfExists(
            'compliance_identity_bindings'
        );

        Schema::dropIfExists(
            'compliance_retention_policies'
        );

        Schema::dropIfExists(
            'compliance_storage_targets'
        );

        Schema::dropIfExists(
            'compliance_collectors'
        );

        Schema::dropIfExists(
            'compliance_routers'
        );

        Schema::dropIfExists(
            'compliance_networks'
        );

        Schema::dropIfExists(
            'compliance_access_grants'
        );

        Schema::dropIfExists(
            'compliance_subscriptions'
        );

        Schema::dropIfExists(
            'compliance_users'
        );

        Schema::dropIfExists(
            'compliance_organizations'
        );

        Schema::dropIfExists(
            'compliance_plans'
        );
    }
};
