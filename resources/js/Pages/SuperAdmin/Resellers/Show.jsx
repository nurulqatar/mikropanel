import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Show({
    reseller,
    usage = {},
    plans = [],
}) {
    const [
        reason,
        setReason,
    ] = useState('');

    const renew = useForm({
        reseller_plan_id:
            reseller.active_subscription
                ?.reseller_plan_id ??
            '',
        validity_days: '',
    });

    const plan = useForm({
        reseller_plan_id:
            reseller.active_subscription
                ?.reseller_plan_id ??
            '',
        validity_days: '',
    });

    return (
        <AppLayout title="Reseller Details">
            <Head
                title={
                    reseller.company_name
                }
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href={route(
                                'superadmin.resellers.index',
                            )}
                            className="font-semibold text-cyan-700"
                        >
                            ← Resellers
                        </Link>

                        <h1 className="mt-2 text-3xl font-black">
                            {reseller.company_name}
                        </h1>

                        <div className="text-slate-500">
                            {reseller.code}
                            {' · '}
                            {reseller.status}
                        </div>
                    </div>

                    <Link
                        href={route(
                            'superadmin.resellers.edit',
                            reseller.id,
                        )}
                        className="rounded-xl bg-slate-700 px-5 py-3 font-bold text-white"
                    >
                        Edit Reseller
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat
                        label="Client Limit"
                        value={
                            usage.client_limit
                        }
                    />

                    <Stat
                        label="Used Clients"
                        value={
                            usage.used_clients
                        }
                    />

                    <Stat
                        label="Remaining"
                        value={
                            usage.remaining_clients
                        }
                    />

                    <Stat
                        label="Wallet"
                        value={`QAR ${money(
                            usage.wallet_balance,
                        )}`}
                    />

                    <Stat
                        label="Operators"
                        value={
                            usage.operators
                        }
                    />

                    <Stat
                        label="Routers"
                        value={
                            usage.routers
                        }
                    />

                    <Stat
                        label="Hotspot Vouchers"
                        value={
                            usage.hotspot_vouchers
                        }
                    />

                    <Stat
                        label="Subscription"
                        value={
                            usage.subscription_usable
                                ? 'Active'
                                : 'Expired'
                        }
                    />
                </div>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-bold">
                        Account
                    </h2>

                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <Info
                            label="Owner"
                            value={
                                reseller.owner_name
                            }
                        />

                        <Info
                            label="Email"
                            value={
                                reseller.email
                            }
                        />

                        <Info
                            label="Phone"
                            value={
                                reseller.phone ||
                                '-'
                            }
                        />

                        <Info
                            label="Panel Login"
                            value={
                                reseller.owner
                                    ?.is_active
                                    ? 'Enabled'
                                    : 'Disabled - pending tenant isolation'
                            }
                        />

                        <Info
                            label="Current Plan"
                            value={
                                reseller
                                    .active_subscription
                                    ?.plan?.name ||
                                '-'
                            }
                        />

                        <Info
                            label="Expires"
                            value={
                                usage.subscription_expires_at ||
                                '-'
                            }
                        />
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            renew.post(
                                route(
                                    'superadmin.resellers.renew',
                                    reseller.id,
                                ),
                                {
                                    preserveScroll:
                                        true,
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Renew Subscription
                        </h2>

                        <select
                            value={
                                renew.data
                                    .reseller_plan_id
                            }
                            onChange={(e) =>
                                renew.setData(
                                    'reseller_plan_id',
                                    e.target.value,
                                )
                            }
                            className="mt-4 w-full rounded-lg border-slate-300"
                        >
                            <option value="">
                                Keep Current Plan
                            </option>

                            {plans.map(
                                (item) => (
                                    <option
                                        key={
                                            item.id
                                        }
                                        value={
                                            item.id
                                        }
                                    >
                                        {item.name}
                                        {' · '}
                                        {
                                            item.client_limit
                                        }{' '}
                                        clients
                                    </option>
                                ),
                            )}
                        </select>

                        <input
                            type="number"
                            min="1"
                            placeholder="Custom days (optional)"
                            value={
                                renew.data
                                    .validity_days
                            }
                            onChange={(e) =>
                                renew.setData(
                                    'validity_days',
                                    e.target.value,
                                )
                            }
                            className="mt-3 w-full rounded-lg border-slate-300"
                        />

                        <button
                            type="submit"
                            className="mt-4 rounded-lg bg-emerald-600 px-5 py-2.5 font-bold text-white"
                        >
                            Renew
                        </button>
                    </form>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            plan.post(
                                route(
                                    'superadmin.resellers.change-plan',
                                    reseller.id,
                                ),
                                {
                                    preserveScroll:
                                        true,
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Change Plan
                        </h2>

                        <select
                            value={
                                plan.data
                                    .reseller_plan_id
                            }
                            onChange={(e) =>
                                plan.setData(
                                    'reseller_plan_id',
                                    e.target.value,
                                )
                            }
                            className="mt-4 w-full rounded-lg border-slate-300"
                        >
                            <option value="">
                                Select Plan
                            </option>

                            {plans.map(
                                (item) => (
                                    <option
                                        key={
                                            item.id
                                        }
                                        value={
                                            item.id
                                        }
                                    >
                                        {item.name}
                                        {' · '}
                                        {
                                            item.client_limit
                                        }{' '}
                                        clients
                                    </option>
                                ),
                            )}
                        </select>

                        <input
                            type="number"
                            min="1"
                            placeholder="Custom days (optional)"
                            value={
                                plan.data
                                    .validity_days
                            }
                            onChange={(e) =>
                                plan.setData(
                                    'validity_days',
                                    e.target.value,
                                )
                            }
                            className="mt-3 w-full rounded-lg border-slate-300"
                        />

                        <button
                            type="submit"
                            className="mt-4 rounded-lg bg-violet-600 px-5 py-2.5 font-bold text-white"
                        >
                            Apply New Plan
                        </button>
                    </form>
                </div>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-bold">
                        Status Control
                    </h2>

                    {reseller.status ===
                    'suspended' ? (
                        <button
                            onClick={() =>
                                router.post(
                                    route(
                                        'superadmin.resellers.reactivate',
                                        reseller.id,
                                    ),
                                    {},
                                    {
                                        preserveScroll:
                                            true,
                                    },
                                )
                            }
                            className="mt-4 rounded-lg bg-emerald-600 px-5 py-2.5 font-bold text-white"
                        >
                            Reactivate Reseller
                        </button>
                    ) : (
                        <>
                            <textarea
                                value={reason}
                                onChange={(e) =>
                                    setReason(
                                        e.target.value,
                                    )
                                }
                                placeholder="Suspension reason"
                                rows="2"
                                className="mt-4 w-full max-w-xl rounded-lg border-slate-300"
                            />

                            <button
                                onClick={() => {
                                    if (
                                        confirm(
                                            'Suspend this reseller?',
                                        )
                                    ) {
                                        router.post(
                                            route(
                                                'superadmin.resellers.suspend',
                                                reseller.id,
                                            ),
                                            {
                                                reason,
                                            },
                                            {
                                                preserveScroll:
                                                    true,
                                            },
                                        );
                                    }
                                }}
                                className="mt-3 rounded-lg bg-red-600 px-5 py-2.5 font-bold text-white"
                            >
                                Suspend Reseller
                            </button>
                        </>
                    )}
                </section>

                <section className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <div className="border-b px-6 py-5 text-xl font-bold">
                        Subscription History
                    </div>

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Plan</Th>
                                <Th>Clients</Th>
                                <Th>Price</Th>
                                <Th>Start</Th>
                                <Th>Expiry</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {reseller.subscriptions
                                ?.slice()
                                .sort(
                                    (a, b) =>
                                        b.id - a.id,
                                )
                                .map(
                                    (sub) => (
                                        <tr key={sub.id}>
                                            <Td>
                                                {sub.plan?.name ||
                                                    '-'}
                                            </Td>

                                            <Td>
                                                {
                                                    sub.client_limit
                                                }
                                            </Td>

                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    sub.price,
                                                )}
                                            </Td>

                                            <Td>
                                                {
                                                    sub.starts_at
                                                }
                                            </Td>

                                            <Td>
                                                {
                                                    sub.expires_at
                                                }
                                            </Td>

                                            <Td>
                                                {
                                                    sub.status
                                                }
                                            </Td>
                                        </tr>
                                    ),
                                )}
                        </tbody>
                    </table>
                </section>
            </div>
        </AppLayout>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-black">
                {value ?? 0}
            </div>
        </div>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div>
            <div className="text-xs font-bold uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-1 font-semibold">
                {value}
            </div>
        </div>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm">
            {children}
        </td>
    );
}
