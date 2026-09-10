import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Dashboard({
    stats = {},
    recentResellers = [],
}) {
    return (
        <AppLayout title="Super Admin">
            <Head title="Super Admin" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-slate-900">
                            Super Admin Dashboard
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Resellers, subscriptions,
                            client capacity and platform
                            control.
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={route(
                                'superadmin.plans.index',
                            )}
                            className="rounded-xl bg-slate-700 px-5 py-3 font-bold text-white"
                        >
                            Reseller Plans
                        </Link>

                        <Link
                            href={route(
                                'superadmin.resellers.create',
                            )}
                            className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white"
                        >
                            Add Reseller
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat
                        label="Total Resellers"
                        value={
                            stats.total_resellers
                        }
                    />

                    <Stat
                        label="Active"
                        value={
                            stats.active_resellers
                        }
                    />

                    <Stat
                        label="Suspended"
                        value={
                            stats.suspended_resellers
                        }
                    />

                    <Stat
                        label="Expiring ≤ 7 Days"
                        value={
                            stats.expiring_7_days
                        }
                    />

                    <Stat
                        label="Expired Subscription"
                        value={
                            stats.expired_subscriptions
                        }
                    />

                    <Stat
                        label="Total Client Slots"
                        value={
                            stats.total_client_slots
                        }
                    />

                    <Stat
                        label="Used Client Slots"
                        value={
                            stats.used_client_slots
                        }
                    />

                    <Stat
                        label="Reseller Wallets"
                        value={`QAR ${money(
                            stats.wallet_balance,
                        )}`}
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b px-6 py-5">
                        <h2 className="text-xl font-bold">
                            Recent Resellers
                        </h2>

                        <Link
                            href={route(
                                'superadmin.resellers.index',
                            )}
                            className="font-semibold text-cyan-700"
                        >
                            View All
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Reseller</Th>
                                    <Th>Plan</Th>
                                    <Th>Clients</Th>
                                    <Th>Remaining</Th>
                                    <Th>Wallet</Th>
                                    <Th>Status</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {recentResellers.map(
                                    (item) => (
                                        <tr
                                            key={
                                                item.id
                                            }
                                        >
                                            <Td>
                                                <Link
                                                    href={route(
                                                        'superadmin.resellers.show',
                                                        item.id,
                                                    )}
                                                    className="font-bold text-cyan-700"
                                                >
                                                    {
                                                        item.company_name
                                                    }
                                                </Link>

                                                <div className="text-xs text-slate-400">
                                                    {
                                                        item.code
                                                    }
                                                </div>
                                            </Td>

                                            <Td>
                                                {item.plan ||
                                                    '-'}
                                            </Td>

                                            <Td>
                                                {
                                                    item.usage
                                                        ?.used_clients
                                                }
                                                /
                                                {
                                                    item.usage
                                                        ?.client_limit
                                                }
                                            </Td>

                                            <Td>
                                                {
                                                    item.usage
                                                        ?.remaining_clients
                                                }
                                            </Td>

                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    item.wallet_balance,
                                                )}
                                            </Td>

                                            <Td>
                                                {
                                                    item.status
                                                }
                                            </Td>
                                        </tr>
                                    ),
                                )}

                                {recentResellers.length ===
                                    0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="p-10 text-center text-slate-400"
                                        >
                                            No reseller yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
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
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black text-slate-900">
                {value ?? 0}
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
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}
