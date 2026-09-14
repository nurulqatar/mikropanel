import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Dashboard({
    reseller = {},
    usage = {},
    stats = {},
    recentClients = [],
    isOwner = false,
}) {
    const expired =
        !usage.subscription_usable;

    return (
        <AppLayout title="Reseller Dashboard">
            <Head title="Reseller Dashboard" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            {reseller.company_name}
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Reseller Dashboard ·{' '}
                            {reseller.code}
                        </p>
                    </div>

                    {isOwner && (
                        <Link
                            href={route(
                                'reseller.operators.index',
                            )}
                            className="rounded-xl bg-violet-600 px-5 py-3 font-bold text-white"
                        >
                            Operators
                        </Link>
                    )}
                </div>

                {(expired ||
                    reseller.status !==
                        'active') && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 font-semibold text-amber-800">
                        Account status:{' '}
                        {reseller.status}.
                        Subscription:{' '}
                        {usage.subscription_usable
                            ? 'active'
                            : 'expired'}.
                        Expiry policy:{' '}
                        {reseller.expiry_mode}.
                    </div>
                )}

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
                            reseller.wallet_balance,
                        )}`}
                    />

                    <Stat
                        label="Active Clients"
                        value={
                            stats.active_clients
                        }
                    />

                    <Stat
                        label="Routers"
                        value={
                            stats.routers
                        }
                    />

                    <Stat
                        label="Today Collection"
                        value={`QAR ${money(
                            stats.today_collection,
                        )}`}
                    />

                    <Stat
                        label="Total Due"
                        value={`QAR ${money(
                            Number(
                                stats.normal_due ??
                                    0,
                            ) +
                                Number(
                                    stats.hotspot_due ??
                                        0,
                                ),
                        )}`}
                    />

                    <Stat
                        label="Hotspot Vouchers"
                        value={
                            stats.hotspot_vouchers
                        }
                    />

                    <Stat
                        label="Hotspot Online"
                        value={
                            stats.online_hotspot
                        }
                    />

                    <Stat
                        label="Operators"
                        value={
                            usage.operators
                        }
                    />

                    <Stat
                        label="Expires"
                        value={
                            usage.subscription_expires_at ||
                            '-'
                        }
                    />
                </div>

                <section className="rounded-2xl border bg-white p-5 shadow-sm">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Quick
                            routeName="clients.index"
                            label="Clients"
                        />

                        <Quick
                            routeName="routers.index"
                            label="Routers"
                        />

                        <Quick
                            routeName="packages.index"
                            label="Packages"
                        />

                        <Quick
                            routeName="ip-ranges.index"
                            label="IP Pools"
                        />

                        <Quick
                            routeName="invoices.index"
                            label="Invoices"
                        />

                        <Quick
                            routeName="payments.index"
                            label="Payments"
                        />

                        <Quick
                            routeName="accounting.index"
                            label="Accounting"
                        />

                        <Quick
                            routeName="hotspot.index"
                            label="Hotspot"
                        />
                    </div>
                </section>

                <section className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <div className="border-b px-5 py-4 text-lg font-bold">
                        Recent Clients
                    </div>

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Client</Th>
                                <Th>IP</Th>
                                <Th>Package</Th>
                                <Th>Router</Th>
                                <Th>Status</Th>
                                <Th>Expiry</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {recentClients.map(
                                (client) => (
                                    <tr
                                        key={
                                            client.id
                                        }
                                    >
                                        <Td>
                                            <Link
                                                href={route(
                                                    'clients.show',
                                                    client.id,
                                                )}
                                                className="font-bold text-cyan-700"
                                            >
                                                {
                                                    client.name
                                                }
                                            </Link>
                                        </Td>

                                        <Td>
                                            {
                                                client.ip_address
                                            }
                                        </Td>

                                        <Td>
                                            {client.package
                                                ?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {client.router
                                                ?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {client.enabled
                                                ? 'Active'
                                                : 'Suspended'}
                                        </Td>

                                        <Td>
                                            {client.expiry_date ||
                                                '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {recentClients.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="p-8 text-center text-slate-400"
                                    >
                                        No client yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </AppLayout>
    );
}

function Quick({
    routeName,
    label,
}) {
    return (
        <Link
            href={route(routeName)}
            className="rounded-xl border bg-slate-50 px-4 py-4 font-bold text-slate-700 transition hover:border-cyan-300 hover:bg-white"
        >
            {label}
        </Link>
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

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-4 py-4 text-sm">
            {children}
        </td>
    );
}
