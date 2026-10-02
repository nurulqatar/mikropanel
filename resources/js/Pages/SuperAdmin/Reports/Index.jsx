import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Index({
    summary = {},
    rows = [],
}) {
    return (
        <SuperAdminLayout title="Reseller Reports">
            <Head title="Reseller Reports" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Reseller Reports
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Capacity, wallet and
                            subscription overview.
                        </p>
                    </div>

                    <a
                        href={route(
                            'superadmin.reports.csv',
                        )}
                        className="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white"
                    >
                        Export CSV
                    </a>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat
                        label="Resellers"
                        value={
                            summary.resellers
                        }
                    />

                    <Stat
                        label="Active"
                        value={
                            summary.active
                        }
                    />

                    <Stat
                        label="Suspended"
                        value={
                            summary.suspended
                        }
                    />

                    <Stat
                        label="Expired"
                        value={
                            summary.expired
                        }
                    />

                    <Stat
                        label="Client Slots"
                        value={
                            summary.total_slots
                        }
                    />

                    <Stat
                        label="Used Slots"
                        value={
                            summary.used_slots
                        }
                    />

                    <Stat
                        label="Wallet Balance"
                        value={`QAR ${money(
                            summary.wallet_balance,
                        )}`}
                    />

                    <Stat
                        label="Month Recharge"
                        value={`QAR ${money(
                            summary.month_recharge,
                        )}`}
                    />
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Reseller</Th>
                                <Th>Plan</Th>
                                <Th>Clients</Th>
                                <Th>Remaining</Th>
                                <Th>Routers</Th>
                                <Th>Operators</Th>
                                <Th>Wallet</Th>
                                <Th>Expiry</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {rows.map(
                                (row) => (
                                    <tr key={row.id}>
                                        <Td>
                                            <Link
                                                href={route(
                                                    'superadmin.resellers.show',
                                                    row.id,
                                                )}
                                                className="font-bold text-cyan-700"
                                            >
                                                {
                                                    row.company_name
                                                }
                                            </Link>

                                            <div className="text-xs text-slate-400">
                                                {row.code}
                                            </div>
                                        </Td>

                                        <Td>
                                            {row.plan}
                                        </Td>

                                        <Td>
                                            {
                                                row.used_clients
                                            }
                                            /
                                            {
                                                row.client_limit
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                row.remaining_clients
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                row.routers
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                row.operators
                                            }
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                row.wallet_balance,
                                            )}
                                        </Td>

                                        <Td>
                                            {row.expires_at ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {
                                                row.status
                                            }
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {!rows.length && (
                                <tr>
                                    <td
                                        colSpan="9"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No reseller yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
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
