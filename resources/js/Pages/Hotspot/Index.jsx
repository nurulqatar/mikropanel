import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const dateTime = (value) =>
    value
        ? new Date(value).toLocaleString()
        : '-';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-cyan-500 focus:ring-cyan-500';

export default function Index({
    servers = [],
    plans = [],
    vouchers = [],
    sessions = [],
    dueInvoices = [],
    recentPayments = [],
    stats = {},
    flash = {},
}) {
    const [
        editingPlanId,
        setEditingPlanId,
    ] = useState(null);

    const [
        selectedVoucher,
        setSelectedVoucher,
    ] = useState(null);

    const [
        selectedInvoice,
        setSelectedInvoice,
    ] = useState(null);

    const planForm = useForm({
        name: '',
        price: '',
        validity_value: 30,
        validity_unit: 'days',
        rate_limit: '',
        shared_users: 1,
        idle_timeout_minutes: 5,
        keepalive_timeout_minutes: 2,
        mac_binding: false,
        enabled: true,
    });

    const generatorForm = useForm({
        hotspot_server_id: '',
        hotspot_plan_id: '',
        quantity: 1,
        prefix: '',
    });

    const saleForm = useForm({
        customer_name: '',
        phone: '',
        sale_type: 'paid',
        received_amount: '',
        payment_method: 'Cash',
        transaction_id: '',
    });

    const paymentForm = useForm({
        amount: '',
        payment_method: 'Cash',
        transaction_id: '',
    });

    const editPlan = (plan) => {
        setEditingPlanId(
            plan.id,
        );

        planForm.setData({
            name: plan.name ?? '',
            price: plan.price ?? '',
            validity_value:
                plan.validity_value ?? 1,
            validity_unit:
                plan.validity_unit ?? 'days',
            rate_limit:
                plan.rate_limit ?? '',
            shared_users:
                plan.shared_users ?? 1,
            idle_timeout_minutes:
                plan.idle_timeout_minutes ?? '',
            keepalive_timeout_minutes:
                plan.keepalive_timeout_minutes ??
                '',
            mac_binding:
                Boolean(plan.mac_binding),
            enabled:
                Boolean(plan.enabled),
        });
    };

    const resetPlan = () => {
        setEditingPlanId(null);

        planForm.reset();

        planForm.setData({
            name: '',
            price: '',
            validity_value: 30,
            validity_unit: 'days',
            rate_limit: '',
            shared_users: 1,
            idle_timeout_minutes: 5,
            keepalive_timeout_minutes: 2,
            mac_binding: false,
            enabled: true,
        });
    };

    const submitPlan = (event) => {
        event.preventDefault();

        if (editingPlanId) {
            planForm.put(
                route(
                    'hotspot.plans.update',
                    editingPlanId,
                ),
                {
                    preserveScroll: true,
                    onSuccess: resetPlan,
                },
            );

            return;
        }

        planForm.post(
            route(
                'hotspot.plans.store',
            ),
            {
                preserveScroll: true,
                onSuccess: resetPlan,
            },
        );
    };

    const removePlan = (plan) => {
        if (
            !confirm(
                `Delete Hotspot plan "${plan.name}"?`,
            )
        ) {
            return;
        }

        router.delete(
            route(
                'hotspot.plans.destroy',
                plan.id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    const openSale = (voucher) => {
        setSelectedVoucher(voucher);

        saleForm.setData({
            customer_name:
                voucher.customer_name ?? '',
            phone:
                voucher.phone ?? '',
            sale_type: 'paid',
            received_amount: '',
            payment_method: 'Cash',
            transaction_id: '',
        });
    };

    const submitSale = (event) => {
        event.preventDefault();

        if (!selectedVoucher) {
            return;
        }

        saleForm.post(
            route(
                'hotspot.vouchers.sell',
                selectedVoucher.id,
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedVoucher(null);
                    saleForm.reset();
                },
            },
        );
    };

    const openPayment = (invoice) => {
        setSelectedInvoice(invoice);

        paymentForm.setData({
            amount:
                Number(
                    invoice.due_amount ?? 0,
                ).toFixed(2),
            payment_method: 'Cash',
            transaction_id: '',
        });
    };

    const submitPayment = (event) => {
        event.preventDefault();

        if (!selectedInvoice) {
            return;
        }

        paymentForm.post(
            route(
                'hotspot.invoices.pay',
                selectedInvoice.id,
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedInvoice(null);
                    paymentForm.reset();
                },
            },
        );
    };

    return (
        <AppLayout title="Hotspot Management">
            <Head title="Hotspot Management" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Hotspot Control Center
                        </h1>

                        <p className="mt-1 text-slate-500">
                            MikroTik Hotspot servers,
                            vouchers, billing and live
                            sessions from one place.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() =>
                            router.post(
                                route(
                                    'hotspot.discover',
                                ),
                                {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white shadow hover:bg-cyan-700"
                    >
                        Discover MikroTik Hotspot
                    </button>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700">
                        {flash.error}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                    <Stat
                        label="Servers"
                        value={stats.servers}
                    />

                    <Stat
                        label="Plans"
                        value={stats.plans}
                    />

                    <Stat
                        label="Unused"
                        value={
                            stats.unused_vouchers
                        }
                    />

                    <Stat
                        label="Sold"
                        value={
                            stats.sold_vouchers
                        }
                    />

                    <Stat
                        label="Active"
                        value={
                            stats.active_vouchers
                        }
                    />

                    <Stat
                        label="Online"
                        value={
                            stats.active_sessions
                        }
                    />

                    <Stat
                        label="Total Due"
                        value={`QAR ${money(
                            stats.total_due,
                        )}`}
                    />

                    <Stat
                        label="Today"
                        value={`QAR ${money(
                            stats.today_collection,
                        )}`}
                    />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <Header
                        title="Hotspot Servers"
                        subtitle="Discovered from enabled MikroTik routers"
                    />

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Server</Th>
                                    <Th>Router</Th>
                                    <Th>Interface</Th>
                                    <Th>Pool</Th>
                                    <Th>Users</Th>
                                    <Th>Online</Th>
                                    <Th>Status</Th>
                                    <Th>Action</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {servers.length === 0 ? (
                                    <Empty
                                        columns={8}
                                        text="No Hotspot server discovered yet. Click Discover MikroTik Hotspot."
                                    />
                                ) : (
                                    servers.map(
                                        (server) => (
                                            <tr
                                                key={
                                                    server.id
                                                }
                                            >
                                                <Td>
                                                    <strong>
                                                        {
                                                            server.name
                                                        }
                                                    </strong>

                                                    <div className="text-xs text-slate-400">
                                                        {
                                                            server.mikrotik_name
                                                        }
                                                    </div>
                                                </Td>

                                                <Td>
                                                    {server
                                                        .router
                                                        ?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {server.interface ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {server.address_pool ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {server.users_count ??
                                                        0}
                                                </Td>

                                                <Td>
                                                    {server.active_sessions_count ??
                                                        0}
                                                </Td>

                                                <Td>
                                                    <Badge
                                                        value={
                                                            server.connected
                                                                ? 'connected'
                                                                : 'offline'
                                                        }
                                                    />
                                                </Td>

                                                <Td>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'hotspot.servers.sync',
                                                                    server.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        className="rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                                                    >
                                                        Sync
                                                    </button>
                                                </Td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <form
                        onSubmit={submitPlan}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold text-slate-900">
                            {editingPlanId
                                ? 'Edit Hotspot Plan'
                                : 'Create Hotspot Plan'}
                        </h2>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Plan Name"
                                error={
                                    planForm
                                        .errors
                                        .name
                                }
                            >
                                <input
                                    value={
                                        planForm
                                            .data
                                            .name
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'name',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field
                                label="Price (QAR)"
                                error={
                                    planForm
                                        .errors
                                        .price
                                }
                            >
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={
                                        planForm
                                            .data
                                            .price
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'price',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Validity">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        planForm
                                            .data
                                            .validity_value
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'validity_value',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Validity Unit">
                                <select
                                    value={
                                        planForm
                                            .data
                                            .validity_unit
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'validity_unit',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="minutes">
                                        Minutes
                                    </option>
                                    <option value="hours">
                                        Hours
                                    </option>
                                    <option value="days">
                                        Days
                                    </option>
                                </select>
                            </Field>

                            <Field label="Rate Limit">
                                <input
                                    value={
                                        planForm
                                            .data
                                            .rate_limit
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'rate_limit',
                                            e.target
                                                .value,
                                        )
                                    }
                                    placeholder="10M/10M"
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Shared Users">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        planForm
                                            .data
                                            .shared_users
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'shared_users',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Idle Timeout (min)">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        planForm
                                            .data
                                            .idle_timeout_minutes
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'idle_timeout_minutes',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Keepalive Timeout (min)">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        planForm
                                            .data
                                            .keepalive_timeout_minutes
                                    }
                                    onChange={(e) =>
                                        planForm.setData(
                                            'keepalive_timeout_minutes',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-5">
                            <Check
                                label="MAC Binding"
                                checked={
                                    planForm
                                        .data
                                        .mac_binding
                                }
                                onChange={(value) =>
                                    planForm.setData(
                                        'mac_binding',
                                        value,
                                    )
                                }
                            />

                            <Check
                                label="Enabled"
                                checked={
                                    planForm
                                        .data
                                        .enabled
                                }
                                onChange={(value) =>
                                    planForm.setData(
                                        'enabled',
                                        value,
                                    )
                                }
                            />
                        </div>

                        <div className="mt-5 flex gap-3">
                            <button
                                type="submit"
                                disabled={
                                    planForm.processing
                                }
                                className="rounded-lg bg-cyan-600 px-4 py-2.5 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                            >
                                {editingPlanId
                                    ? 'Update Plan'
                                    : 'Create Plan'}
                            </button>

                            {editingPlanId && (
                                <button
                                    type="button"
                                    onClick={
                                        resetPlan
                                    }
                                    className="rounded-lg bg-slate-200 px-4 py-2.5 font-semibold text-slate-700"
                                >
                                    Cancel
                                </button>
                            )}
                        </div>
                    </form>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            generatorForm.post(
                                route(
                                    'hotspot.vouchers.generate',
                                ),
                                {
                                    preserveScroll: true,
                                },
                            );
                        }}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold text-slate-900">
                            Generate Vouchers
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Default credentials use
                            six numeric digits.
                        </p>

                        <div className="mt-5 space-y-4">
                            <Field
                                label="Hotspot Server"
                                error={
                                    generatorForm
                                        .errors
                                        .hotspot_server_id
                                }
                            >
                                <select
                                    value={
                                        generatorForm
                                            .data
                                            .hotspot_server_id
                                    }
                                    onChange={(e) =>
                                        generatorForm.setData(
                                            'hotspot_server_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="">
                                        Select Server
                                    </option>

                                    {servers
                                        .filter(
                                            (s) =>
                                                s.enabled,
                                        )
                                        .map(
                                            (
                                                server,
                                            ) => (
                                                <option
                                                    key={
                                                        server.id
                                                    }
                                                    value={
                                                        server.id
                                                    }
                                                >
                                                    {
                                                        server.name
                                                    }
                                                </option>
                                            ),
                                        )}
                                </select>
                            </Field>

                            <Field
                                label="Plan"
                                error={
                                    generatorForm
                                        .errors
                                        .hotspot_plan_id
                                }
                            >
                                <select
                                    value={
                                        generatorForm
                                            .data
                                            .hotspot_plan_id
                                    }
                                    onChange={(e) =>
                                        generatorForm.setData(
                                            'hotspot_plan_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="">
                                        Select Plan
                                    </option>

                                    {plans
                                        .filter(
                                            (p) =>
                                                p.enabled,
                                        )
                                        .map(
                                            (plan) => (
                                                <option
                                                    key={
                                                        plan.id
                                                    }
                                                    value={
                                                        plan.id
                                                    }
                                                >
                                                    {
                                                        plan.name
                                                    }{' '}
                                                    - QAR{' '}
                                                    {money(
                                                        plan.price,
                                                    )}
                                                </option>
                                            ),
                                        )}
                                </select>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Quantity"
                                    error={
                                        generatorForm
                                            .errors
                                            .quantity
                                    }
                                >
                                    <input
                                        type="number"
                                        min="1"
                                        max="500"
                                        value={
                                            generatorForm
                                                .data
                                                .quantity
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            generatorForm.setData(
                                                'quantity',
                                                e
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Prefix (Optional)"
                                    error={
                                        generatorForm
                                            .errors
                                            .prefix
                                    }
                                >
                                    <input
                                        value={
                                            generatorForm
                                                .data
                                                .prefix
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            generatorForm.setData(
                                                'prefix',
                                                e
                                                    .target
                                                    .value,
                                            )
                                        }
                                        placeholder="VIP"
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={
                                generatorForm.processing
                            }
                            className="mt-5 rounded-lg bg-violet-600 px-5 py-2.5 font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            Generate Voucher
                        </button>
                    </form>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <Header
                        title="Hotspot Plans"
                        subtitle="Price, validity, bandwidth and device policy"
                    />

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Name</Th>
                                    <Th>Price</Th>
                                    <Th>Validity</Th>
                                    <Th>Rate</Th>
                                    <Th>Users</Th>
                                    <Th>MAC</Th>
                                    <Th>Status</Th>
                                    <Th>Actions</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {plans.length === 0 ? (
                                    <Empty
                                        columns={8}
                                        text="No Hotspot plans created."
                                    />
                                ) : (
                                    plans.map(
                                        (plan) => (
                                            <tr
                                                key={
                                                    plan.id
                                                }
                                            >
                                                <Td>
                                                    {
                                                        plan.name
                                                    }
                                                </Td>

                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        plan.price,
                                                    )}
                                                </Td>

                                                <Td>
                                                    {
                                                        plan.validity_value
                                                    }{' '}
                                                    {
                                                        plan.validity_unit
                                                    }
                                                </Td>

                                                <Td>
                                                    {plan.rate_limit ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {
                                                        plan.shared_users
                                                    }
                                                </Td>

                                                <Td>
                                                    {plan.mac_binding
                                                        ? 'Yes'
                                                        : 'No'}
                                                </Td>

                                                <Td>
                                                    <Badge
                                                        value={
                                                            plan.enabled
                                                                ? 'enabled'
                                                                : 'disabled'
                                                        }
                                                    />
                                                </Td>

                                                <Td>
                                                    <div className="flex gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                editPlan(
                                                                    plan,
                                                                )
                                                            }
                                                            className="rounded bg-amber-500 px-3 py-1.5 text-sm font-semibold text-white"
                                                        >
                                                            Edit
                                                        </button>

                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removePlan(
                                                                    plan,
                                                                )
                                                            }
                                                            className="rounded bg-red-600 px-3 py-1.5 text-sm font-semibold text-white"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </Td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <Header
                        title="Voucher Inventory"
                        subtitle="Latest 100 vouchers"
                    />

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Voucher</Th>
                                    <Th>Server</Th>
                                    <Th>Plan</Th>
                                    <Th>Price</Th>
                                    <Th>Status</Th>
                                    <Th>Billing</Th>
                                    <Th>Expiry</Th>
                                    <Th>Action</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {vouchers.length ===
                                0 ? (
                                    <Empty
                                        columns={8}
                                        text="No vouchers generated."
                                    />
                                ) : (
                                    vouchers.map(
                                        (
                                            voucher,
                                        ) => (
                                            <tr
                                                key={
                                                    voucher.id
                                                }
                                            >
                                                <Td>
                                                    <div className="font-mono font-bold">
                                                        {
                                                            voucher.username
                                                        }
                                                    </div>

                                                    <div className="font-mono text-xs text-slate-500">
                                                        Pass:{' '}
                                                        {
                                                            voucher.password
                                                        }
                                                    </div>
                                                </Td>

                                                <Td>
                                                    {voucher
                                                        .server
                                                        ?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {voucher
                                                        .plan
                                                        ?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        voucher
                                                            .plan
                                                            ?.price,
                                                    )}
                                                </Td>

                                                <Td>
                                                    <Badge
                                                        value={
                                                            voucher.status
                                                        }
                                                    />
                                                </Td>

                                                <Td>
                                                    {voucher.invoice ? (
                                                        <div>
                                                            <Badge
                                                                value={
                                                                    voucher
                                                                        .invoice
                                                                        .status
                                                                }
                                                            />

                                                            <div className="mt-1 text-xs text-slate-500">
                                                                Due QAR{' '}
                                                                {money(
                                                                    voucher
                                                                        .invoice
                                                                        .due_amount,
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-400">
                                                            Unsold
                                                        </span>
                                                    )}
                                                </Td>

                                                <Td>
                                                    {dateTime(
                                                        voucher.expires_at,
                                                    )}
                                                </Td>

                                                <Td>
                                                    {!voucher.invoice &&
                                                        voucher.status ===
                                                            'unused' && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    openSale(
                                                                        voucher,
                                                                    )
                                                                }
                                                                className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white"
                                                            >
                                                                Sell
                                                            </button>
                                                        )}
                                                </Td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {selectedVoucher && (
                    <form
                        onSubmit={submitSale}
                        className="rounded-2xl border-2 border-emerald-200 bg-emerald-50 p-6"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">
                                    Sell Voucher{' '}
                                    {
                                        selectedVoucher.username
                                    }
                                </h2>

                                <p className="text-sm text-slate-600">
                                    Package bill: QAR{' '}
                                    {money(
                                        selectedVoucher
                                            .plan
                                            ?.price,
                                    )}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    setSelectedVoucher(
                                        null,
                                    )
                                }
                                className="rounded bg-white px-3 py-2 text-sm font-semibold text-slate-600"
                            >
                                Close
                            </button>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Field label="Customer Name">
                                <input
                                    value={
                                        saleForm
                                            .data
                                            .customer_name
                                    }
                                    onChange={(e) =>
                                        saleForm.setData(
                                            'customer_name',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Phone">
                                <input
                                    value={
                                        saleForm
                                            .data
                                            .phone
                                    }
                                    onChange={(e) =>
                                        saleForm.setData(
                                            'phone',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field
                                label="Payment Status"
                                error={
                                    saleForm.errors
                                        .sale_type
                                }
                            >
                                <select
                                    value={
                                        saleForm
                                            .data
                                            .sale_type
                                    }
                                    onChange={(e) =>
                                        saleForm.setData(
                                            'sale_type',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="paid">
                                        Paid
                                    </option>
                                    <option value="due">
                                        Due
                                    </option>
                                    <option value="partial">
                                        Partial
                                    </option>
                                </select>
                            </Field>

                            {saleForm.data
                                .sale_type ===
                                'partial' && (
                                <Field
                                    label="Amount Received"
                                    error={
                                        saleForm
                                            .errors
                                            .received_amount
                                    }
                                >
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={
                                            saleForm
                                                .data
                                                .received_amount
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            saleForm.setData(
                                                'received_amount',
                                                e
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>
                            )}

                            {saleForm.data
                                .sale_type !==
                                'due' && (
                                <Field label="Payment Method">
                                    <PaymentMethod
                                        value={
                                            saleForm
                                                .data
                                                .payment_method
                                        }
                                        onChange={(
                                            value,
                                        ) =>
                                            saleForm.setData(
                                                'payment_method',
                                                value,
                                            )
                                        }
                                    />
                                </Field>
                            )}

                            {saleForm.data
                                .sale_type !==
                                'due' && (
                                <Field label="Transaction ID">
                                    <input
                                        value={
                                            saleForm
                                                .data
                                                .transaction_id
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            saleForm.setData(
                                                'transaction_id',
                                                e
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={
                                saleForm.processing
                            }
                            className="mt-5 rounded-lg bg-emerald-600 px-5 py-3 font-bold text-white disabled:opacity-50"
                        >
                            Confirm Sale & Provision
                        </button>
                    </form>
                )}

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <Header
                        title="Outstanding Hotspot Bills"
                        subtitle="Due and partial voucher invoices"
                    />

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Invoice</Th>
                                    <Th>Voucher</Th>
                                    <Th>Plan</Th>
                                    <Th>Bill</Th>
                                    <Th>Paid</Th>
                                    <Th>Due</Th>
                                    <Th>Status</Th>
                                    <Th>Action</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {dueInvoices.length ===
                                0 ? (
                                    <Empty
                                        columns={8}
                                        text="No outstanding Hotspot bill."
                                    />
                                ) : (
                                    dueInvoices.map(
                                        (
                                            invoice,
                                        ) => (
                                            <tr
                                                key={
                                                    invoice.id
                                                }
                                            >
                                                <Td>
                                                    {
                                                        invoice.invoice_no
                                                    }
                                                </Td>

                                                <Td>
                                                    {invoice
                                                        .voucher
                                                        ?.username ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {invoice
                                                        .voucher
                                                        ?.plan
                                                        ?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        invoice.amount,
                                                    )}
                                                </Td>

                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        invoice.paid_amount,
                                                    )}
                                                </Td>

                                                <Td>
                                                    <strong>
                                                        QAR{' '}
                                                        {money(
                                                            invoice.due_amount,
                                                        )}
                                                    </strong>
                                                </Td>

                                                <Td>
                                                    <Badge
                                                        value={
                                                            invoice.status
                                                        }
                                                    />
                                                </Td>

                                                <Td>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openPayment(
                                                                invoice,
                                                            )
                                                        }
                                                        className="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white"
                                                    >
                                                        Receive
                                                    </button>
                                                </Td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {selectedInvoice && (
                    <form
                        onSubmit={
                            submitPayment
                        }
                        className="rounded-2xl border-2 border-cyan-200 bg-cyan-50 p-6"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">
                                    Receive Hotspot Due
                                </h2>

                                <p className="text-sm text-slate-600">
                                    {
                                        selectedInvoice.invoice_no
                                    }{' '}
                                    · Remaining QAR{' '}
                                    {money(
                                        selectedInvoice.due_amount,
                                    )}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    setSelectedInvoice(
                                        null,
                                    )
                                }
                                className="rounded bg-white px-3 py-2 text-sm font-semibold text-slate-600"
                            >
                                Close
                            </button>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-3">
                            <Field
                                label="Amount"
                                error={
                                    paymentForm
                                        .errors
                                        .amount
                                }
                            >
                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={
                                        paymentForm
                                            .data
                                            .amount
                                    }
                                    onChange={(e) =>
                                        paymentForm.setData(
                                            'amount',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Payment Method">
                                <PaymentMethod
                                    value={
                                        paymentForm
                                            .data
                                            .payment_method
                                    }
                                    onChange={(
                                        value,
                                    ) =>
                                        paymentForm.setData(
                                            'payment_method',
                                            value,
                                        )
                                    }
                                />
                            </Field>

                            <Field label="Transaction ID">
                                <input
                                    value={
                                        paymentForm
                                            .data
                                            .transaction_id
                                    }
                                    onChange={(e) =>
                                        paymentForm.setData(
                                            'transaction_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <button
                            type="submit"
                            disabled={
                                paymentForm.processing
                            }
                            className="mt-5 rounded-lg bg-cyan-600 px-5 py-3 font-bold text-white disabled:opacity-50"
                        >
                            Confirm Payment
                        </button>
                    </form>
                )}

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <Header
                        title="Live Hotspot Sessions"
                        subtitle="Current MikroTik active users"
                    />

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>User</Th>
                                    <Th>Server</Th>
                                    <Th>IP</Th>
                                    <Th>MAC</Th>
                                    <Th>Login By</Th>
                                    <Th>Upload</Th>
                                    <Th>Download</Th>
                                    <Th>Last Seen</Th>
                                    <Th>Action</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {sessions.length ===
                                0 ? (
                                    <Empty
                                        columns={9}
                                        text="No active Hotspot session."
                                    />
                                ) : (
                                    sessions.map(
                                        (
                                            session,
                                        ) => (
                                            <tr
                                                key={
                                                    session.id
                                                }
                                            >
                                                <Td>
                                                    <strong>
                                                        {
                                                            session.username
                                                        }
                                                    </strong>
                                                </Td>

                                                <Td>
                                                    {session
                                                        .server
                                                        ?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {session.address ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {session.mac_address ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {session.login_by ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {bytes(
                                                        session.bytes_in,
                                                    )}
                                                </Td>

                                                <Td>
                                                    {bytes(
                                                        session.bytes_out,
                                                    )}
                                                </Td>

                                                <Td>
                                                    {dateTime(
                                                        session.last_seen_at,
                                                    )}
                                                </Td>

                                                <Td>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    `Disconnect ${session.username}?`,
                                                                )
                                                            ) {
                                                                router.post(
                                                                    route(
                                                                        'hotspot.sessions.disconnect',
                                                                        session.id,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                        className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white"
                                                    >
                                                        Disconnect
                                                    </button>
                                                </Td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <Header
                        title="Recent Hotspot Payments"
                        subtitle={`This month collection: QAR ${money(
                            stats.month_collection,
                        )}`}
                    />

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Date</Th>
                                    <Th>Voucher</Th>
                                    <Th>Invoice</Th>
                                    <Th>Amount</Th>
                                    <Th>Method</Th>
                                    <Th>Received By</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {recentPayments.length ===
                                0 ? (
                                    <Empty
                                        columns={6}
                                        text="No Hotspot payment recorded."
                                    />
                                ) : (
                                    recentPayments.map(
                                        (
                                            payment,
                                        ) => (
                                            <tr
                                                key={
                                                    payment.id
                                                }
                                            >
                                                <Td>
                                                    {
                                                        payment.payment_date
                                                    }
                                                </Td>

                                                <Td>
                                                    {payment
                                                        .voucher
                                                        ?.username ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {payment
                                                        .invoice
                                                        ?.invoice_no ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        payment.amount,
                                                    )}
                                                </Td>

                                                <Td>
                                                    {
                                                        payment.payment_method
                                                    }
                                                </Td>

                                                <Td>
                                                    {payment.received_by
                                                        ? `User #${payment.received_by}`
                                                        : '-'}
                                                </Td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Header({
    title,
    subtitle,
}) {
    return (
        <div className="border-b border-slate-200 px-6 py-5">
            <h2 className="text-xl font-bold text-slate-900">
                {title}
            </h2>

            <p className="mt-1 text-sm text-slate-500">
                {subtitle}
            </p>
        </div>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-bold text-slate-900">
                {value ?? 0}
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-1 block text-sm text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function Check({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) =>
                    onChange(
                        e.target.checked,
                    )
                }
                className="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
            />

            {label}
        </label>
    );
}

function PaymentMethod({
    value,
    onChange,
}) {
    return (
        <select
            value={value}
            onChange={(e) =>
                onChange(
                    e.target.value,
                )
            }
            className={inputClass}
        >
            <option value="Cash">
                Cash
            </option>

            <option value="Bank Transfer">
                Bank Transfer
            </option>

            <option value="Ooredoo Money">
                Ooredoo Money
            </option>

            <option value="Card">
                Card
            </option>

            <option value="PayPal">
                PayPal
            </option>

            <option value="Manual Adjustment">
                Manual Adjustment
            </option>
        </select>
    );
}

function Badge({ value }) {
    const normalized =
        String(value ?? '')
            .toLowerCase();

    const className =
        normalized === 'paid' ||
        normalized === 'active' ||
        normalized === 'connected' ||
        normalized === 'enabled'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'partial' ||
                normalized === 'unused'
              ? 'bg-amber-100 text-amber-700'
              : normalized === 'unpaid' ||
                  normalized === 'expired' ||
                  normalized === 'offline' ||
                  normalized === 'disabled'
                ? 'bg-red-100 text-red-700'
                : 'bg-slate-100 text-slate-700';

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${className}`}
        >
            {value || '-'}
        </span>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}

function Empty({
    columns,
    text,
}) {
    return (
        <tr>
            <td
                colSpan={columns}
                className="px-5 py-10 text-center text-slate-400"
            >
                {text}
            </td>
        </tr>
    );
}

function bytes(value) {
    let size =
        Number(value ?? 0);

    if (size < 1024) {
        return `${size} B`;
    }

    if (
        size <
        1024 * 1024
    ) {
        return `${(
            size / 1024
        ).toFixed(1)} KB`;
    }

    if (
        size <
        1024 * 1024 * 1024
    ) {
        return `${(
            size /
            1024 /
            1024
        ).toFixed(1)} MB`;
    }

    return `${(
        size /
        1024 /
        1024 /
        1024
    ).toFixed(2)} GB`;
}
