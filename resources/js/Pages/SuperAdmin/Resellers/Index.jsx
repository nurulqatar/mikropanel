import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Index({
    resellers = [],
}) {
    return (
        <AppLayout title="Resellers">
            <Head title="Resellers" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Resellers
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Manage reseller capacity,
                            subscriptions and access.
                        </p>
                    </div>

                    <Link
                        href={route(
                            'superadmin.resellers.create',
                        )}
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white"
                    >
                        Add Reseller
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Reseller</Th>
                                <Th>Plan</Th>
                                <Th>Clients</Th>
                                <Th>Remaining</Th>
                                <Th>Expiry</Th>
                                <Th>Wallet</Th>
                                <Th>Status</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {resellers.map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Td>
                                            <strong>
                                                {
                                                    item.company_name
                                                }
                                            </strong>

                                            <div className="text-xs text-slate-400">
                                                {item.code}
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
                                            {item.expires_at ||
                                                '-'}
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

                                        <Td>
                                            <Link
                                                href={route(
                                                    'superadmin.resellers.show',
                                                    item.id,
                                                )}
                                                className="rounded bg-slate-700 px-3 py-2 text-sm font-bold text-white"
                                            >
                                                Manage
                                            </Link>
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {resellers.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No reseller created yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
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
