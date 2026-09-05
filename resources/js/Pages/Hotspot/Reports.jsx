import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';
import { useState } from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Reports({
    report,
}) {
    const [
        from,
        setFrom,
    ] = useState(
        report.from,
    );

    const [
        to,
        setTo,
    ] = useState(
        report.to,
    );

    const apply = () => {
        router.get(
            route(
                'hotspot.reports.index',
            ),
            {
                from,
                to,
            },
            {
                preserveState: true,
            },
        );
    };

    const query = new URLSearchParams({
        from,
        to,
    }).toString();

    return (
        <AppLayout title="Hotspot Reports">
            <Head title="Hotspot Reports" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Link
                            href={route(
                                'hotspot.index',
                            )}
                            className="font-semibold text-cyan-700"
                        >
                            ← Hotspot
                        </Link>

                        <h1 className="mt-2 text-3xl font-bold">
                            Hotspot Reports
                        </h1>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <a
                            href={`${route(
                                'hotspot.reports.csv',
                            )}?${query}`}
                            className="rounded-lg bg-emerald-600 px-4 py-2.5 font-semibold text-white"
                        >
                            CSV
                        </a>

                        <a
                            href={`${route(
                                'hotspot.reports.pdf',
                            )}?${query}`}
                            className="rounded-lg bg-violet-600 px-4 py-2.5 font-semibold text-white"
                        >
                            PDF
                        </a>
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-4 rounded-xl border bg-white p-5">
                    <Field label="From">
                        <input
                            type="date"
                            value={from}
                            onChange={(e) =>
                                setFrom(
                                    e.target.value,
                                )
                            }
                            className="rounded-lg border-slate-300"
                        />
                    </Field>

                    <Field label="To">
                        <input
                            type="date"
                            value={to}
                            onChange={(e) =>
                                setTo(
                                    e.target.value,
                                )
                            }
                            className="rounded-lg border-slate-300"
                        />
                    </Field>

                    <button
                        onClick={apply}
                        className="rounded-lg bg-cyan-600 px-5 py-2.5 font-semibold text-white"
                    >
                        Apply
                    </button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat
                        label="Collection"
                        value={`QAR ${money(
                            report.summary
                                .collection,
                        )}`}
                    />

                    <Stat
                        label="Billed"
                        value={`QAR ${money(
                            report.summary
                                .billed,
                        )}`}
                    />

                    <Stat
                        label="Period Due"
                        value={`QAR ${money(
                            report.summary
                                .period_due,
                        )}`}
                    />

                    <Stat
                        label="All Outstanding"
                        value={`QAR ${money(
                            report.summary
                                .all_due,
                        )}`}
                    />

                    <Stat
                        label="Sold Vouchers"
                        value={
                            report.summary
                                .sold_vouchers
                        }
                    />

                    <Stat
                        label="Active Vouchers"
                        value={
                            report.summary
                                .active_vouchers
                        }
                    />

                    <Stat
                        label="Online"
                        value={
                            report.summary
                                .online_sessions
                        }
                    />
                </div>

                <section className="overflow-x-auto rounded-xl border bg-white">
                    <Header title="Operator Collection" />

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Operator</Th>
                                <Th>Transactions</Th>
                                <Th>Collection</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {report.operators.map(
                                (row, index) => (
                                    <tr key={index}>
                                        <Td>
                                            {row.name ||
                                                'System / Unknown'}
                                        </Td>

                                        <Td>
                                            {
                                                row.transactions
                                            }
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                row.total,
                                            )}
                                        </Td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </section>

                <section className="overflow-x-auto rounded-xl border bg-white">
                    <Header title="Payment Ledger" />

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Date</Th>
                                <Th>Voucher</Th>
                                <Th>Invoice</Th>
                                <Th>Amount</Th>
                                <Th>Method</Th>
                                <Th>Transaction</Th>
                                <Th>Operator</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {report.payments.map(
                                (row) => (
                                    <tr key={row.id}>
                                        <Td>
                                            {
                                                row.payment_date
                                            }
                                        </Td>

                                        <Td>
                                            {row.username ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {row.invoice_no ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                row.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            {
                                                row.payment_method
                                            }
                                        </Td>

                                        <Td>
                                            {row.transaction_id ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {row.received_by_name ||
                                                '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {report.payments.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No payment in this period.
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

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold">
                {value}
            </div>
        </div>
    );
}

function Header({ title }) {
    return (
        <div className="border-b px-5 py-4 text-lg font-bold">
            {title}
        </div>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>
            {children}
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="px-5 py-4 text-sm">
            {children}
        </td>
    );
}
