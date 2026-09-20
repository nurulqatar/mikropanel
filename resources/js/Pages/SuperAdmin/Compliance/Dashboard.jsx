import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';

export default function Dashboard({
    stats = {},
    plans = [],
    rentals = [],
    organizations = [],
    resellers = [],
    hotels = [],
}) {
    return (
        <SuperAdminLayout title="Network Compliance">
            <Head title="Network Compliance" />

            <div className="space-y-8">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                        Commercial Rental Control
                    </div>

                    <h1 className="mt-3 text-3xl font-black">
                        Rent Compliance Service Before Hardware Is Connected
                    </h1>

                    <p className="mt-4 max-w-4xl leading-7 text-slate-300">
                        Sell Logging, Filtering or Complete service now. A standalone customer gets a Compliance login immediately. Existing Company/Hotel customers get same-panel access. Router, collector and NAS/S3 can be connected later.
                    </p>

                    <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label="Plans" value={stats.plans} />
                        <Stat label="Organizations" value={stats.organizations} />
                        <Stat label="Active Rentals" value={stats.active_rentals} />
                        <Stat label="Pending Hardware" value={stats.pending_hardware} />
                    </div>
                </section>

                <section>
                    <h2 className="text-2xl font-black">
                        Plans & Price
                    </h2>

                    <div className="mt-4 grid gap-5 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard
                                key={plan.id}
                                plan={plan}
                            />
                        ))}
                    </div>
                </section>

                <div className="grid gap-7 xl:grid-cols-2">
                    <StandaloneForm plans={plans} />
                    <AddonForm
                        plans={plans}
                        resellers={resellers}
                        hotels={hotels}
                    />
                </div>

                <RentalTable rentals={rentals} />

                <PasswordReset organizations={organizations} />

                <section className="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm leading-6 text-amber-950">
                    <strong>Pending Hardware is normal:</strong>{' '}
                    renting does not require a live MikroTik or NAS. No RouterOS rule is changed by rent, renewal, suspension, reactivation or expiry. Real Logging/Filtering becomes operational after the customer later connects supported network equipment.
                </section>
            </div>
        </SuperAdminLayout>
    );
}

function PlanCard({ plan }) {
    const form = useForm({
        name: plan.name ?? '',
        monthly_price:
            plan.monthly_price ?? 0,
        call_for_price:
            Boolean(plan.call_for_price),
        enabled:
            Boolean(plan.enabled),
        description:
            plan.description ?? '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();

                form.put(
                    route(
                        'superadmin.compliance.plans.update',
                        plan.id,
                    ),
                    {
                        preserveScroll: true,
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6"
        >
            <div className="text-xs font-black uppercase text-cyan-700">
                {plan.service_type}
            </div>

            <div className="mt-4 space-y-4">
                <Field
                    label="Plan Name"
                    value={form.data.name}
                    onChange={(v) =>
                        form.setData('name', v)
                    }
                />

                <Field
                    label="Monthly Price (QAR)"
                    type="number"
                    step="0.01"
                    value={form.data.monthly_price}
                    onChange={(v) =>
                        form.setData(
                            'monthly_price',
                            v,
                        )
                    }
                />

                <Area
                    label="Description"
                    value={form.data.description}
                    onChange={(v) =>
                        form.setData(
                            'description',
                            v,
                        )
                    }
                />

                <Check
                    label="Call for Price"
                    checked={form.data.call_for_price}
                    onChange={(v) =>
                        form.setData(
                            'call_for_price',
                            v,
                        )
                    }
                />

                <Check
                    label="Enabled"
                    checked={form.data.enabled}
                    onChange={(v) =>
                        form.setData('enabled', v)
                    }
                />

                <button className="w-full rounded-xl bg-slate-950 px-4 py-3 font-black text-white">
                    Save Plan
                </button>
            </div>
        </form>
    );
}

function StandaloneForm({ plans }) {
    const form = useForm({
        organization_name: '',
        organization_code: '',
        owner_name: '',
        owner_email: '',
        password: '',
        plan_id: plans[0]?.id ?? '',
        days: 30,
        amount: 0,
        payment_status: 'unpaid',
        payment_method: 'Cash',
        payment_reference: '',
        notes: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();

                form.post(
                    route(
                        'superadmin.compliance.rentals.standalone',
                    ),
                    {
                        preserveScroll: true,
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6"
        >
            <h2 className="text-2xl font-black">
                Rent Standalone
            </h2>

            <p className="mt-2 text-sm text-slate-500">
                Creates organization, login and subscription. No router required.
            </p>

            <Errors errors={form.errors} />

            <div className="mt-5 grid gap-4 md:grid-cols-2">
                <Field
                    label="Organization"
                    value={form.data.organization_name}
                    onChange={(v) =>
                        form.setData(
                            'organization_name',
                            v,
                        )
                    }
                />

                <Field
                    label="Code (optional)"
                    value={form.data.organization_code}
                    onChange={(v) =>
                        form.setData(
                            'organization_code',
                            v,
                        )
                    }
                />

                <Field
                    label="Owner Name"
                    value={form.data.owner_name}
                    onChange={(v) =>
                        form.setData(
                            'owner_name',
                            v,
                        )
                    }
                />

                <Field
                    label="Owner Email"
                    type="email"
                    value={form.data.owner_email}
                    onChange={(v) =>
                        form.setData(
                            'owner_email',
                            v,
                        )
                    }
                />

                <Field
                    label="Temporary Password"
                    type="password"
                    value={form.data.password}
                    onChange={(v) =>
                        form.setData(
                            'password',
                            v,
                        )
                    }
                />

                <PlanSelect
                    plans={plans}
                    value={form.data.plan_id}
                    onChange={(v) =>
                        form.setData(
                            'plan_id',
                            v,
                        )
                    }
                />

                <Field
                    label="Days"
                    type="number"
                    value={form.data.days}
                    onChange={(v) =>
                        form.setData('days', v)
                    }
                />

                <Field
                    label="Amount QAR"
                    type="number"
                    step="0.01"
                    value={form.data.amount}
                    onChange={(v) =>
                        form.setData(
                            'amount',
                            v,
                        )
                    }
                />

                <PaymentStatus
                    value={form.data.payment_status}
                    onChange={(v) =>
                        form.setData(
                            'payment_status',
                            v,
                        )
                    }
                />

                <Field
                    label="Payment Method"
                    value={form.data.payment_method}
                    onChange={(v) =>
                        form.setData(
                            'payment_method',
                            v,
                        )
                    }
                />

                <Field
                    label="Payment Reference"
                    value={form.data.payment_reference}
                    onChange={(v) =>
                        form.setData(
                            'payment_reference',
                            v,
                        )
                    }
                />
            </div>

            <div className="mt-4">
                <Area
                    label="Notes"
                    value={form.data.notes}
                    onChange={(v) =>
                        form.setData('notes', v)
                    }
                />
            </div>

            <button className="mt-5 rounded-xl bg-cyan-600 px-6 py-3 font-black text-white">
                Rent Standalone
            </button>
        </form>
    );
}

function AddonForm({
    plans,
    resellers,
    hotels,
}) {
    const [type, setType] =
        useState('reseller');

    const sources =
        type === 'hotel'
            ? hotels
            : resellers;

    const form = useForm({
        source_type: 'reseller',
        source_id: '',
        plan_id: plans[0]?.id ?? '',
        days: 30,
        amount: 0,
        payment_status: 'unpaid',
        payment_method: 'Cash',
        payment_reference: '',
        notes: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();

                form.post(
                    route(
                        'superadmin.compliance.rentals.addon',
                    ),
                    {
                        preserveScroll: true,
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6"
        >
            <h2 className="text-2xl font-black">
                Rent Existing Customer Add-on
            </h2>

            <p className="mt-2 text-sm text-slate-500">
                MAC/Hotspot Company or Hotel gets the service inside its existing panel.
            </p>

            <Errors errors={form.errors} />

            <div className="mt-5 grid gap-4 md:grid-cols-2">
                <Select
                    label="Customer Type"
                    value={type}
                    onChange={(v) => {
                        setType(v);
                        form.setData(
                            'source_type',
                            v,
                        );
                        form.setData(
                            'source_id',
                            '',
                        );
                    }}
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

                <Select
                    label="Customer"
                    value={form.data.source_id}
                    onChange={(v) =>
                        form.setData(
                            'source_id',
                            v,
                        )
                    }
                    options={[
                        [
                            '',
                            sources.length
                                ? 'Select customer'
                                : 'No customer available yet',
                        ],
                        ...sources.map(
                            (item) => [
                                item.id,
                                item.label,
                            ],
                        ),
                    ]}
                />

                <PlanSelect
                    plans={plans}
                    value={form.data.plan_id}
                    onChange={(v) =>
                        form.setData(
                            'plan_id',
                            v,
                        )
                    }
                />

                <Field
                    label="Days"
                    type="number"
                    value={form.data.days}
                    onChange={(v) =>
                        form.setData('days', v)
                    }
                />

                <Field
                    label="Amount QAR"
                    type="number"
                    step="0.01"
                    value={form.data.amount}
                    onChange={(v) =>
                        form.setData(
                            'amount',
                            v,
                        )
                    }
                />

                <PaymentStatus
                    value={form.data.payment_status}
                    onChange={(v) =>
                        form.setData(
                            'payment_status',
                            v,
                        )
                    }
                />

                <Field
                    label="Payment Method"
                    value={form.data.payment_method}
                    onChange={(v) =>
                        form.setData(
                            'payment_method',
                            v,
                        )
                    }
                />

                <Field
                    label="Payment Reference"
                    value={form.data.payment_reference}
                    onChange={(v) =>
                        form.setData(
                            'payment_reference',
                            v,
                        )
                    }
                />
            </div>

            <div className="mt-4">
                <Area
                    label="Notes"
                    value={form.data.notes}
                    onChange={(v) =>
                        form.setData('notes', v)
                    }
                />
            </div>

            <button
                disabled={!sources.length}
                className="mt-5 rounded-xl bg-violet-600 px-6 py-3 font-black text-white disabled:opacity-40"
            >
                Rent Add-on
            </button>
        </form>
    );
}

function RentalTable({ rentals }) {
    return (
        <section className="rounded-3xl border bg-white p-6">
            <h2 className="text-2xl font-black">
                Rental Customers
            </h2>

            <div className="mt-5 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th className="px-3 py-3">
                                Customer
                            </th>
                            <th className="px-3 py-3">
                                Service
                            </th>
                            <th className="px-3 py-3">
                                Expiry
                            </th>
                            <th className="px-3 py-3">
                                Payment
                            </th>
                            <th className="px-3 py-3">
                                Setup
                            </th>
                            <th className="px-3 py-3">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {rentals.map((item) => (
                            <tr
                                key={item.id}
                                className="border-t"
                            >
                                <td className="px-3 py-4">
                                    <div className="font-black">
                                        {item.customer_name}
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        {item.rental_type}
                                        {' · '}
                                        {item.status}
                                    </div>
                                </td>

                                <td className="px-3 py-4">
                                    <div className="font-bold">
                                        {item.plan_name}
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        L:{' '}
                                        {item.logging_enabled
                                            ? 'Yes'
                                            : 'No'}
                                        {' · '}
                                        F:{' '}
                                        {item.filtering_enabled
                                            ? 'Yes'
                                            : 'No'}
                                    </div>
                                </td>

                                <td className="px-3 py-4">
                                    {item.expires_at}
                                </td>

                                <td className="px-3 py-4">
                                    QAR {item.amount}
                                    <div className="text-xs uppercase text-slate-500">
                                        {item.payment_status}
                                    </div>
                                </td>

                                <td className="px-3 py-4">
                                    <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-black uppercase text-violet-700">
                                        {item.setup_state}
                                    </span>
                                </td>

                                <td className="px-3 py-4">
                                    <RentalActions
                                        item={item}
                                    />
                                </td>
                            </tr>
                        ))}

                        {!rentals.length && (
                            <tr>
                                <td
                                    colSpan="6"
                                    className="px-3 py-10 text-center text-slate-400"
                                >
                                    No rental yet. Hardware is not required to create the first customer.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function RentalActions({ item }) {
    const renew = useForm({
        days: 30,
        amount: Number(item.amount ?? 0),
        payment_status: 'paid',
        payment_method: 'Cash',
        payment_reference: '',
    });

    return (
        <div className="min-w-52 space-y-2">
            <div className="grid grid-cols-2 gap-2">
                <input
                    type="number"
                    value={renew.data.days}
                    onChange={(e) =>
                        renew.setData(
                            'days',
                            e.target.value,
                        )
                    }
                    className="rounded-lg border px-2 py-2"
                />

                <input
                    type="number"
                    step="0.01"
                    value={renew.data.amount}
                    onChange={(e) =>
                        renew.setData(
                            'amount',
                            e.target.value,
                        )
                    }
                    className="rounded-lg border px-2 py-2"
                />
            </div>

            <button
                type="button"
                onClick={() =>
                    renew.post(
                        route(
                            'superadmin.compliance.rentals.renew',
                            item.id,
                        ),
                        {
                            preserveScroll: true,
                        },
                    )
                }
                className="w-full rounded-lg bg-emerald-600 px-3 py-2 font-black text-white"
            >
                Renew
            </button>

            {item.status === 'suspended' ? (
                <button
                    type="button"
                    onClick={() =>
                        router.post(
                            route(
                                'superadmin.compliance.rentals.reactivate',
                                item.id,
                            ),
                            {
                                days: 30,
                            },
                        )
                    }
                    className="w-full rounded-lg bg-cyan-600 px-3 py-2 font-black text-white"
                >
                    Reactivate
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() =>
                        router.post(
                            route(
                                'superadmin.compliance.rentals.suspend',
                                item.id,
                            ),
                        )
                    }
                    className="w-full rounded-lg bg-amber-500 px-3 py-2 font-black"
                >
                    Suspend
                </button>
            )}
        </div>
    );
}

function PasswordReset({ organizations }) {
    const options = useMemo(
        () =>
            organizations.filter(
                (item) =>
                    item.account_type
                    === 'standalone',
            ),
        [organizations],
    );

    const form = useForm({
        organization_id:
            options[0]?.id ?? '',
        email: '',
        password: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();

                if (!form.data.organization_id) {
                    return;
                }

                form.post(
                    route(
                        'superadmin.compliance.organizations.reset-password',
                        form.data.organization_id,
                    ),
                    {
                        preserveScroll: true,
                    },
                );
            }}
            className="rounded-3xl border bg-white p-6"
        >
            <h2 className="text-xl font-black">
                Standalone Password Reset
            </h2>

            <div className="mt-5 grid gap-4 md:grid-cols-4">
                <Select
                    label="Organization"
                    value={form.data.organization_id}
                    onChange={(v) =>
                        form.setData(
                            'organization_id',
                            v,
                        )
                    }
                    options={[
                        ['', 'Select'],
                        ...options.map(
                            (item) => [
                                item.id,
                                item.name,
                            ],
                        ),
                    ]}
                />

                <Field
                    label="Email"
                    type="email"
                    value={form.data.email}
                    onChange={(v) =>
                        form.setData('email', v)
                    }
                />

                <Field
                    label="New Password"
                    type="password"
                    value={form.data.password}
                    onChange={(v) =>
                        form.setData(
                            'password',
                            v,
                        )
                    }
                />

                <div className="flex items-end">
                    <button className="w-full rounded-xl bg-slate-950 px-4 py-3 font-black text-white">
                        Reset
                    </button>
                </div>
            </div>
        </form>
    );
}

function PlanSelect({
    plans,
    value,
    onChange,
}) {
    return (
        <Select
            label="Plan"
            value={value}
            onChange={onChange}
            options={plans
                .filter((p) =>
                    Boolean(p.enabled)
                )
                .map((p) => [
                    p.id,
                    p.call_for_price
                        ? `${p.name} · Call for Price`
                        : `${p.name} · QAR ${p.monthly_price}`,
                ])}
        />
    );
}

function PaymentStatus({
    value,
    onChange,
}) {
    return (
        <Select
            label="Payment Status"
            value={value}
            onChange={onChange}
            options={[
                ['unpaid', 'Unpaid'],
                ['paid', 'Paid'],
                ['partial', 'Partial'],
                ['waived', 'Waived'],
            ]}
        />
    );
}

function Stat({
    label,
    value = 0,
}) {
    return (
        <div className="rounded-2xl bg-white/10 p-4">
            <div className="text-2xl font-black">
                {value ?? 0}
            </div>
            <div className="mt-1 text-xs font-bold uppercase text-slate-400">
                {label}
            </div>
        </div>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
    step,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">
                {label}
            </span>
            <input
                type={type}
                step={step}
                value={value}
                onChange={(e) =>
                    onChange(e.target.value)
                }
                className="w-full rounded-xl border-slate-300"
            />
        </label>
    );
}

function Area({
    label,
    value,
    onChange,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">
                {label}
            </span>
            <textarea
                rows="3"
                value={value}
                onChange={(e) =>
                    onChange(e.target.value)
                }
                className="w-full rounded-xl border-slate-300"
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
            <span className="mb-2 block text-sm font-bold">
                {label}
            </span>
            <select
                value={value}
                onChange={(e) =>
                    onChange(e.target.value)
                }
                className="w-full rounded-xl border-slate-300"
            >
                {options.map(([id, text]) => (
                    <option key={id} value={id}>
                        {text}
                    </option>
                ))}
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
        <label className="flex items-center gap-3 text-sm font-bold">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) =>
                    onChange(e.target.checked)
                }
            />
            {label}
        </label>
    );
}

function Errors({ errors }) {
    const items = Object.values(
        errors ?? {},
    );

    if (!items.length) {
        return null;
    }

    return (
        <div className="mt-4 rounded-xl bg-rose-50 p-4 text-sm font-bold text-rose-700">
            {items.map((message) => (
                <div key={message}>
                    {message}
                </div>
            ))}
        </div>
    );
}
