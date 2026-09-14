import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Index({
    recharges = [],
    summary = {},
}) {
    return (
        <AppLayout title="Reseller Recharges">
            <Head title="Reseller Recharges" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Recharge History
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Approved reseller wallet
                            recharge records.
                        </p>
                    </div>

                    <Link
                        href={route(
                            'superadmin.wallet.index',
                        )}
                        className="rounded-xl bg-slate-700 px-5 py-3 font-bold text-white"
                    >
                        Wallet Ledger
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat
                        label="Today Recharge"
                        value={`QAR ${money(
                            summary.today_recharge,
                        )}`}
                    />

                    <Stat
                        label="This Month"
                        value={`QAR ${money(
                            summary.month_recharge,
                        )}`}
                    />

                    <Stat
                        label="All Wallet Balance"
                        value={`QAR ${money(
                            summary.total_wallet_balance,
                        )}`}
                    />
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Recharge</Th>
                                <Th>Date</Th>
                                <Th>Reseller</Th>
                                <Th>Amount</Th>
                                <Th>Method</Th>
                                <Th>Reference</Th>
                                <Th>Status</Th>
                                <Th>Approved By</Th>
                                <Th>Balance After</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {recharges.map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Td>
                                            <span className="font-mono text-xs">
                                                {
                                                    item.recharge_no
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            {
                                                item.approved_at
                                            }
                                        </Td>

                                        <Td>
                                            {item.reseller ? (
                                                <Link
                                                    href={route(
                                                        'superadmin.resellers.show',
                                                        item.reseller.id,
                                                    )}
                                                    className="font-bold text-cyan-700"
                                                >
                                                    {
                                                        item.reseller
                                                            .company_name
                                                    }
                                                </Link>
                                            ) : (
                                                '-'
                                            )}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                item.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            {item.payment_method ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {item.reference ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {
                                                item.status
                                            }
                                        </Td>

                                        <Td>
                                            {item.approver?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {item.wallet_transaction
                                                ? `QAR ${money(
                                                    item.wallet_transaction
                                                        .balance_after,
                                                )}`
                                                : '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {recharges.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="9"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No reseller recharge yet.
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
                {value}
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
