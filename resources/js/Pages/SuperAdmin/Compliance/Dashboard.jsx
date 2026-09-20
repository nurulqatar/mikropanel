import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

export default function Dashboard({
    plans = [],
    organizations = [],
    stats = {},
}) {
    return (
        <SuperAdminLayout title="Network Compliance">
            <Head title="Network Compliance" />

            <div className="space-y-8">
                <div className="grid gap-4 md:grid-cols-4">
                    <Stat
                        label="Organizations"
                        value={
                            stats.organizations
                            ?? 0
                        }
                    />

                    <Stat
                        label="Logging"
                        value={
                            stats.loggingSubscriptions
                            ?? 0
                        }
                    />

                    <Stat
                        label="Filtering"
                        value={
                            stats.filteringSubscriptions
                            ?? 0
                        }
                    />

                    <Stat
                        label="Routers"
                        value={
                            stats.routers
                            ?? 0
                        }
                    />
                </div>

                <div className="grid gap-7 xl:grid-cols-2">
                    <PlanForm />

                    <OrganizationForm />
                </div>

                <SubscriptionForm
                    plans={plans}
                    organizations={
                        organizations
                    }
                />

                <AccessGrantForm
                    organizations={
                        organizations
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel title="Compliance Plans">
                        {plans.length
                            === 0 && (
                            <Empty text="No plans yet." />
                        )}

                        {plans.map(
                            (plan) => (
                                <Card
                                    key={
                                        plan.id
                                    }
                                    title={
                                        plan.name
                                    }
                                    subtitle={`${plan.service_type} · ${
                                        plan.call_for_price
                                            ? 'Call for Price'
                                            : `QAR ${plan.monthly_price}/month`
                                    }`}
                                />
                            ),
                        )}
                    </Panel>

                    <Panel title="Organizations">
                        {organizations.length
                            === 0 && (
                            <Empty text="No organizations yet." />
                        )}

                        {organizations.map(
                            (org) => (
                                <Card
                                    key={
                                        org.id
                                    }
                                    title={
                                        org.name
                                    }
                                    subtitle={`${org.account_type} · ${org.networks_count} network(s) · ${org.routers_count} router(s)`}
                                />
                            ),
                        )}
                    </Panel>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

function PlanForm() {
    const form = useForm({
        name: '',
        code: '',
        service_type:
            'logging',
        monthly_price: '0',
        call_for_price:
            false,
        router_limit: '',
        retention_days: '',
        filter_rule_limit:
            '',
        description: '',
    });

    return (
        <form
            onSubmit={(
                event,
            ) => {
                event.preventDefault();

                form.post(
                    route(
                        'superadmin.compliance.plans.store',
                    ),
                    {
                        preserveScroll:
                            true,

                        onSuccess:
                            () =>
                                form.reset(),
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6 shadow-sm"
        >
            <h2 className="text-xl font-black">
                Create Plan
            </h2>

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <Field
                    label="Plan Name"
                    value={
                        form.data.name
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'name',
                            value,
                        )
                    }
                />

                <Field
                    label="Code"
                    value={
                        form.data.code
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'code',
                            value,
                        )
                    }
                />

                <label>
                    <span className="mb-1 block text-sm font-bold">
                        Service
                    </span>

                    <select
                        value={
                            form.data
                                .service_type
                        }
                        onChange={(
                            event,
                        ) =>
                            form.setData(
                                'service_type',
                                event.target
                                    .value,
                            )
                        }
                        className="w-full rounded-xl border px-3 py-2.5"
                    >
                        <option value="logging">
                            Logging
                            Only
                        </option>

                        <option value="filtering">
                            Filtering
                            Only
                        </option>

                        <option value="bundle">
                            Logging +
                            Filtering
                        </option>
                    </select>
                </label>

                <Field
                    label="Monthly Price"
                    value={
                        form.data
                            .monthly_price
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'monthly_price',
                            value,
                        )
                    }
                />

                <Field
                    label="Router Limit"
                    value={
                        form.data
                            .router_limit
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'router_limit',
                            value,
                        )
                    }
                />

                <Field
                    label="Retention Days"
                    value={
                        form.data
                            .retention_days
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'retention_days',
                            value,
                        )
                    }
                />
            </div>

            <label className="mt-4 flex items-center gap-2 text-sm font-bold">
                <input
                    type="checkbox"
                    checked={
                        form.data
                            .call_for_price
                    }
                    onChange={(
                        event,
                    ) =>
                        form.setData(
                            'call_for_price',
                            event.target
                                .checked,
                        )
                    }
                />

                Call for Price
            </label>

            <button className="mt-5 rounded-xl bg-slate-950 px-5 py-3 font-black text-white">
                Create Plan
            </button>
        </form>
    );
}

function OrganizationForm() {
    const form = useForm({
        name: '',
        code: '',
        account_type:
            'standalone',
        legal_profile:
            'private_enterprise',
        owner_name: '',
        owner_email: '',
        owner_password: '',
    });

    return (
        <form
            onSubmit={(
                event,
            ) => {
                event.preventDefault();

                form.post(
                    route(
                        'superadmin.compliance.organizations.store',
                    ),
                    {
                        preserveScroll:
                            true,

                        onSuccess:
                            () =>
                                form.reset(),
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6 shadow-sm"
        >
            <h2 className="text-xl font-black">
                Create Organization
            </h2>

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <Field
                    label="Organization"
                    value={
                        form.data.name
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'name',
                            value,
                        )
                    }
                />

                <Field
                    label="Code"
                    value={
                        form.data.code
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'code',
                            value,
                        )
                    }
                />

                <label>
                    <span className="mb-1 block text-sm font-bold">
                        Account Type
                    </span>

                    <select
                        value={
                            form.data
                                .account_type
                        }
                        onChange={(
                            event,
                        ) =>
                            form.setData(
                                'account_type',
                                event.target
                                    .value,
                            )
                        }
                        className="w-full rounded-xl border px-3 py-2.5"
                    >
                        <option value="standalone">
                            Standalone
                        </option>

                        <option value="reseller">
                            MAC /
                            Hotspot
                        </option>

                        <option value="hotel">
                            Hotel
                            Hotspot
                        </option>

                        <option value="mixed">
                            Mixed
                        </option>
                    </select>
                </label>

                <label>
                    <span className="mb-1 block text-sm font-bold">
                        Legal Profile
                    </span>

                    <select
                        value={
                            form.data
                                .legal_profile
                        }
                        onChange={(
                            event,
                        ) =>
                            form.setData(
                                'legal_profile',
                                event.target
                                    .value,
                            )
                        }
                        className="w-full rounded-xl border px-3 py-2.5"
                    >
                        <option value="private_enterprise">
                            Private
                            Enterprise
                        </option>

                        <option value="service_provider">
                            Service
                            Provider
                        </option>

                        <option value="government_affiliated">
                            Government /
                            Affiliated
                        </option>

                        <option value="custom">
                            Custom
                        </option>
                    </select>
                </label>

                <Field
                    label="Owner Name"
                    value={
                        form.data
                            .owner_name
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'owner_name',
                            value,
                        )
                    }
                />

                <Field
                    label="Owner Email"
                    value={
                        form.data
                            .owner_email
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'owner_email',
                            value,
                        )
                    }
                />

                <Field
                    type="password"
                    label="Owner Password"
                    value={
                        form.data
                            .owner_password
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'owner_password',
                            value,
                        )
                    }
                />
            </div>

            <button className="mt-5 rounded-xl bg-cyan-600 px-5 py-3 font-black text-white">
                Create Organization
            </button>
        </form>
    );
}

function SubscriptionForm({
    plans,
    organizations,
}) {
    const form = useForm({
        organization_id:
            '',
        plan_id: '',
        starts_at: '',
        expires_at: '',
    });

    return (
        <form
            onSubmit={(
                event,
            ) => {
                event.preventDefault();

                form.post(
                    route(
                        'superadmin.compliance.subscriptions.store',
                    ),
                    {
                        preserveScroll:
                            true,
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6 shadow-sm"
        >
            <h2 className="text-xl font-black">
                Activate Subscription
            </h2>

            <p className="mt-1 text-sm text-slate-500">
                Logging,
                Filtering and
                Combined plans are
                independently
                assignable.
            </p>

            <div className="mt-5 grid gap-4 md:grid-cols-4">
                <Select
                    label="Organization"
                    value={
                        form.data
                            .organization_id
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'organization_id',
                            value,
                        )
                    }
                    options={
                        organizations.map(
                            (
                                item,
                            ) => [
                                item.id,
                                item.name,
                            ],
                        )
                    }
                />

                <Select
                    label="Plan"
                    value={
                        form.data
                            .plan_id
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'plan_id',
                            value,
                        )
                    }
                    options={
                        plans.map(
                            (
                                item,
                            ) => [
                                item.id,
                                `${item.name} (${item.service_type})`,
                            ],
                        )
                    }
                />

                <Field
                    type="datetime-local"
                    label="Start"
                    value={
                        form.data
                            .starts_at
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'starts_at',
                            value,
                        )
                    }
                />

                <Field
                    type="datetime-local"
                    label="Expiry"
                    value={
                        form.data
                            .expires_at
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'expires_at',
                            value,
                        )
                    }
                />
            </div>

            <button className="mt-5 rounded-xl bg-emerald-600 px-5 py-3 font-black text-white">
                Activate
            </button>
        </form>
    );
}

function AccessGrantForm({
    organizations,
}) {
    const form = useForm({
        organization_id:
            '',
        source_type:
            'reseller',
        source_id: '',
        logging_enabled:
            true,
        filtering_enabled:
            true,
    });

    return (
        <form
            onSubmit={(
                event,
            ) => {
                event.preventDefault();

                form.post(
                    route(
                        'superadmin.compliance.grants.store',
                    ),
                    {
                        preserveScroll:
                            true,
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6 shadow-sm"
        >
            <h2 className="text-xl font-black">
                Link Existing
                MikroPanel Customer
            </h2>

            <p className="mt-1 text-sm text-slate-500">
                Existing MAC,
                Hotspot or Hotel
                customer can access
                Compliance through
                the same platform
                once entitlement is
                enabled.
            </p>

            <div className="mt-5 grid gap-4 md:grid-cols-3">
                <Select
                    label="Organization"
                    value={
                        form.data
                            .organization_id
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'organization_id',
                            value,
                        )
                    }
                    options={
                        organizations.map(
                            (
                                item,
                            ) => [
                                item.id,
                                item.name,
                            ],
                        )
                    }
                />

                <Select
                    label="Existing Module"
                    value={
                        form.data
                            .source_type
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'source_type',
                            value,
                        )
                    }
                    options={[
                        [
                            'reseller',
                            'MAC / Hotspot Company',
                        ],
                        [
                            'hotel',
                            'Hotel Hotspot',
                        ],
                    ]}
                />

                <Field
                    label="Existing Account ID"
                    value={
                        form.data
                            .source_id
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'source_id',
                            value,
                        )
                    }
                />
            </div>

            <div className="mt-4 flex flex-wrap gap-5">
                <Check
                    label="Logging"
                    checked={
                        form.data
                            .logging_enabled
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'logging_enabled',
                            value,
                        )
                    }
                />

                <Check
                    label="Filtering"
                    checked={
                        form.data
                            .filtering_enabled
                    }
                    onChange={(
                        value,
                    ) =>
                        form.setData(
                            'filtering_enabled',
                            value,
                        )
                    }
                />
            </div>

            <button className="mt-5 rounded-xl bg-violet-600 px-5 py-3 font-black text-white">
                Save Link
            </button>
        </form>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-black">
                {value}
            </div>
        </div>
    );
}

function Panel({
    title,
    children,
}) {
    return (
        <div className="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 className="text-xl font-black">
                {title}
            </h2>

            <div className="mt-5 space-y-3">
                {children}
            </div>
        </div>
    );
}

function Card({
    title,
    subtitle,
}) {
    return (
        <div className="rounded-2xl border p-4">
            <div className="font-black">
                {title}
            </div>

            <div className="mt-1 text-sm text-slate-500">
                {subtitle}
            </div>
        </div>
    );
}

function Empty({
    text,
}) {
    return (
        <div className="text-sm text-slate-500">
            {text}
        </div>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-bold">
                {label}
            </span>

            <input
                type={type}
                value={value}
                onChange={(
                    event,
                ) =>
                    onChange(
                        event.target
                            .value,
                    )
                }
                className="w-full rounded-xl border px-3 py-2.5"
            />
        </label>
    );
}

function Select({
    label,
    value,
    onChange,
    options,
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-bold">
                {label}
            </span>

            <select
                value={value}
                onChange={(
                    event,
                ) =>
                    onChange(
                        event.target
                            .value,
                    )
                }
                className="w-full rounded-xl border px-3 py-2.5"
            >
                <option value="">
                    Select
                </option>

                {options.map(
                    ([
                        id,
                        text,
                    ]) => (
                        <option
                            key={id}
                            value={id}
                        >
                            {text}
                        </option>
                    ),
                )}
            </select>
        </label>
    );
}

function Check({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center gap-2 text-sm font-bold">
            <input
                type="checkbox"
                checked={checked}
                onChange={(
                    event,
                ) =>
                    onChange(
                        event.target
                            .checked,
                    )
                }
            />

            {label}
        </label>
    );
}
