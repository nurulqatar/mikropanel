import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Index({
    transactions = [],
    resellers = [],
    filters = {},
    summary = {},
}) {
    const applyFilter = (
        key,
        value,
    ) => {
        const next = {
            ...filters,
            [key]: value,
        };

        router.get(
            route(
                'superadmin.wallet.index',
            ),
            next,
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <AppLayout title="Reseller Wallet Ledger">
            <Head title="Reseller Wallet Ledger" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Wallet Ledger
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Complete reseller credit,
                        debit and adjustment history.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Stat
                        label="Wallet Balance"
                        value={`QAR ${money(
                            summary.total_wallet_balance,
                        )}`}
                    />

                    <Stat
                        label="Total Credits"
                        value={`QAR ${money(
                            summary.total_credits,
                        )}`}
                    />

                    <Stat
                        label="Total Debits"
                        value={`QAR ${money(
                            summary.total_debits,
                        )}`}
                    />

                    <Stat
                        label="Today Recharge"
                        value={`QAR ${money(
                            summary.today_recharge,
                        )}`}
                    />

                    <Stat
                        label="Month Recharge"
                        value={`QAR ${money(
                            summary.month_recharge,
                        )}`}
                    />
                </div>

                <div className="flex flex-wrap gap-3 rounded-2xl border bg-white p-4 shadow-sm">
                    <select
                        value={
                            filters.reseller_id ??
                            ''
                        }
                        onChange={(e) =>
                            applyFilter(
                                'reseller_id',
                                e.target.value,
                            )
                        }
                        className="rounded-lg border-slate-300"
                    >
                        <option value="">
                            All Resellers
                        </option>

                        {resellers.map(
                            (reseller) => (
                                <option
                                    key={
                                        reseller.id
                                    }
                                    value={
                                        reseller.id
                                    }
                                >
                                    {
                                        reseller.company_name
                                    }
                                </option>
                            ),
                        )}
                    </select>

                    <select
                        value={
                            filters.direction ??
                            ''
                        }
                        onChange={(e) =>
                            applyFilter(
                                'direction',
                                e.target.value,
                            )
                        }
                        className="rounded-lg border-slate-300"
                    >
                        <option value="">
                            Credit + Debit
                        </option>
                        <option value="credit">
                            Credit
                        </option>
                        <option value="debit">
                            Debit
                        </option>
                    </select>

                    <Link
                        href={route(
                            'superadmin.recharges.index',
                        )}
                        className="rounded-lg bg-violet-600 px-4 py-2 font-bold text-white"
                    >
                        Recharge History
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Transaction</Th>
                                <Th>Date</Th>
                                <Th>Reseller</Th>
                                <Th>Type</Th>
                                <Th>Direction</Th>
                                <Th>Amount</Th>
                                <Th>Before</Th>
                                <Th>After</Th>
                                <Th>Method</Th>
                                <Th>Reference</Th>
                                <Th>By</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {transactions.map(
                                (item) => (
                                    <tr
                                        key={
                                            item.id
                                        }
                                    >
                                        <Td>
                                            <span className="font-mono text-xs">
                                                {
                                                    item.transaction_no
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            {
                                                item.created_at
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
                                            {
                                                item.type
                                            }
                                        </Td>

                                        <Td>
                                            <span
                                                className={
                                                    item.direction ===
                                                    'credit'
                                                        ? 'font-bold text-emerald-600'
                                                        : 'font-bold text-red-600'
                                                }
                                            >
                                                {
                                                    item.direction
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                item.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                item.balance_before,
                                            )}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                item.balance_after,
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
                                            {item.creator?.name ||
                                                '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {transactions.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="11"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No wallet transaction yet.
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
