import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Index({
    invoices = [],
    flash = {},
}) {
    const paidCount = invoices.filter(
        (invoice) => invoice.status === 'paid',
    ).length;

    const pendingCount = invoices.filter(
        (invoice) =>
            invoice.status !== 'paid' &&
            invoice.status !== 'cancelled',
    ).length;

    const totalDue = invoices.reduce(
        (total, invoice) =>
            total + numberValue(invoice.due_amount),
        0,
    );

    const deleteInvoice = (invoice) => {
        if (
            !confirm(
                `Delete invoice ${invoice.invoice_no}?`,
            )
        ) {
            return;
        }

        router.delete(
            route('invoices.destroy', invoice.id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Invoices">
            <Head title="Invoices" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Invoices
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Create, download and print professional invoices
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {invoices.length > 0 && (
                            <>
                                <a
                                    href={route(
                                        'invoices.print-all',
                                    )}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Print All
                                </a>

                                <a
                                    href={route(
                                        'invoices.download-all',
                                    )}
                                    className="rounded-xl bg-violet-600 px-4 py-3 font-semibold text-white hover:bg-violet-700"
                                >
                                    Download All PDF
                                </a>
                            </>
                        )}

                        <Link
                            href={route(
                                'invoices.create',
                            )}
                            className="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white hover:bg-cyan-700"
                        >
                            + Create Invoice
                        </Link>
                    </div>
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

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total Invoices"
                        value={invoices.length}
                        icon="🧾"
                    />

                    <SummaryCard
                        label="Paid Invoices"
                        value={paidCount}
                        icon="✅"
                    />

                    <SummaryCard
                        label="Pending Invoices"
                        value={pendingCount}
                        icon="⏳"
                    />

                    <SummaryCard
                        label="Total Due"
                        value={`QAR ${formatMoney(
                            totalDue,
                        )}`}
                        icon="💰"
                    />
                </div>

                {invoices.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <div className="text-6xl">
                            🧾
                        </div>

                        <h2 className="mt-5 text-2xl font-bold text-slate-800">
                            No Invoice Found
                        </h2>

                        <p className="mt-2 text-slate-500">
                            Create the first client invoice.
                        </p>

                        <Link
                            href={route(
                                'invoices.create',
                            )}
                            className="mt-6 inline-flex rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700"
                        >
                            Create Invoice
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>
                                            Invoice
                                        </TableHead>

                                        <TableHead>
                                            Client
                                        </TableHead>

                                        <TableHead>
                                            Amount
                                        </TableHead>

                                        <TableHead>
                                            Paid
                                        </TableHead>

                                        <TableHead>
                                            Due
                                        </TableHead>

                                        <TableHead>
                                            Date
                                        </TableHead>

                                        <TableHead>
                                            Status
                                        </TableHead>

                                        <TableHead>
                                            Actions
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {invoices.map(
                                        (invoice) => (
                                            <tr
                                                key={
                                                    invoice.id
                                                }
                                                className="hover:bg-slate-50"
                                            >
                                                <td className="whitespace-nowrap px-5 py-4">
                                                    <div className="font-mono font-bold text-slate-800">
                                                        {
                                                            invoice.invoice_no
                                                        }
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-400">
                                                        Billing:{' '}
                                                        {formatDate(
                                                            invoice.billing_month,
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-800">
                                                        {invoice
                                                            .client
                                                            ?.name ||
                                                            '-'}
                                                    </div>

                                                    <div className="mt-1 font-mono text-xs text-slate-400">
                                                        {invoice
                                                            .client
                                                            ?.client_code ||
                                                            '-'}
                                                    </div>
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 font-bold text-slate-800">
                                                    QAR{' '}
                                                    {formatMoney(
                                                        invoice.amount,
                                                    )}
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 font-semibold text-emerald-600">
                                                    QAR{' '}
                                                    {formatMoney(
                                                        invoice.paid_amount,
                                                    )}
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 font-bold text-red-600">
                                                    QAR{' '}
                                                    {formatMoney(
                                                        invoice.due_amount,
                                                    )}
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                                    {formatDate(
                                                        invoice.issue_date,
                                                    )}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <StatusBadge
                                                        status={
                                                            invoice.status
                                                        }
                                                    />
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="flex flex-wrap gap-2">
                                                        <a
                                                            href={route(
                                                                'invoices.print',
                                                                invoice.id,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                                        >
                                                            Print
                                                        </a>

                                                        <a
                                                            href={route(
                                                                'invoices.download',
                                                                invoice.id,
                                                            )}
                                                            className="rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                                                        >
                                                            PDF
                                                        </a>

                                                        {numberValue(
                                                            invoice.due_amount,
                                                        ) >
                                                            0 && (
                                                            <Link
                                                                href={route(
                                                                    'payments.create',
                                                                )}
                                                                className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                                                            >
                                                                Payment
                                                            </Link>
                                                        )}

                                                        <Link
                                                            href={route(
                                                                'invoices.edit',
                                                                invoice.id,
                                                            )}
                                                            className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                                                        >
                                                            Edit
                                                        </Link>

                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                deleteInvoice(
                                                                    invoice,
                                                                )
                                                            }
                                                            className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    label,
    value,
    icon,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-2xl font-bold text-slate-800">
                        {value}
                    </p>
                </div>

                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 text-2xl">
                    {icon}
                </div>
            </div>
        </div>
    );
}

function TableHead({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
            {children}
        </th>
    );
}

function StatusBadge({ status }) {
    const styles = {
        paid:
            'bg-emerald-100 text-emerald-700',
        partial:
            'bg-amber-100 text-amber-700',
        unpaid:
            'bg-red-100 text-red-700',
        overdue:
            'bg-rose-100 text-rose-700',
        cancelled:
            'bg-slate-200 text-slate-700',
    };

    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-bold ${
                styles[status] ||
                'bg-slate-100 text-slate-700'
            }`}
        >
            {String(
                status || 'unknown',
            ).toUpperCase()}
        </span>
    );
}

function numberValue(value) {
    const number = Number(value);

    return Number.isFinite(number)
        ? number
        : 0;
}

function formatMoney(value) {
    return numberValue(value).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

function formatDate(value) {
    return value
        ? String(value).slice(0, 10)
        : '-';
}
