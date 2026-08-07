import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Index({
    payments = [],
    flash = {},
}) {
    const today = localDateString();

    const totalCollected = payments.reduce(
        (total, payment) =>
            total + numberValue(payment.amount),
        0,
    );

    const todayPayments = payments.filter(
        (payment) =>
            dateValue(payment.payment_date) === today,
    );

    const todayCollected = todayPayments.reduce(
        (total, payment) =>
            total + numberValue(payment.amount),
        0,
    );

    const uniqueClients = new Set(
        payments
            .map((payment) => payment.client_id)
            .filter(Boolean),
    ).size;

    const deletePayment = (payment) => {
        if (
            !confirm(
                `Delete payment for ${
                    payment.client?.name || 'this client'
                }?\n\nInvoice totals will be recalculated.`,
            )
        ) {
            return;
        }

        router.delete(
            route('payments.destroy', payment.id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Payments">
            <Head title="Payments" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Payments
                        </h1>

                        <p className="mt-1 text-slate-500">
                            View client payment history and receive invoice payments
                        </p>
                    </div>

                    <Link
                        href={route('payments.create')}
                        className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        <span className="text-lg">
                            +
                        </span>

                        Receive Payment
                    </Link>
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
                        label="Total Payments"
                        value={payments.length}
                        description="Recorded transactions"
                        icon="🧾"
                    />

                    <SummaryCard
                        label="Total Collected"
                        value={formatMoney(totalCollected)}
                        description="All payment records"
                        icon="💰"
                    />

                    <SummaryCard
                        label="Collected Today"
                        value={formatMoney(todayCollected)}
                        description={`${todayPayments.length} payments today`}
                        icon="📅"
                    />

                    <SummaryCard
                        label="Paying Clients"
                        value={uniqueClients}
                        description="Unique clients with payments"
                        icon="👥"
                    />
                </div>

                {payments.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <div className="text-6xl">
                            💳
                        </div>

                        <h2 className="mt-5 text-2xl font-bold text-slate-800">
                            No Payment Found
                        </h2>

                        <p className="mx-auto mt-2 max-w-md text-slate-500">
                            Receive a payment against an unpaid invoice to begin the payment history.
                        </p>

                        <Link
                            href={route('payments.create')}
                            className="mt-6 inline-flex rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-700"
                        >
                            Receive First Payment
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h2 className="text-lg font-bold text-slate-800">
                                Payment History
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Complete list of received client payments
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>
                                            Client
                                        </TableHead>

                                        <TableHead>
                                            Invoice
                                        </TableHead>

                                        <TableHead>
                                            Amount
                                        </TableHead>

                                        <TableHead>
                                            Payment Date
                                        </TableHead>

                                        <TableHead>
                                            Method
                                        </TableHead>

                                        <TableHead>
                                            Transaction ID
                                        </TableHead>

                                        <TableHead>
                                            Received By
                                        </TableHead>

                                        <TableHead>
                                            Actions
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {payments.map((payment) => (
                                        <tr
                                            key={payment.id}
                                            className="transition hover:bg-slate-50"
                                        >
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-800">
                                                    {payment.client?.name ||
                                                        '-'}
                                                </div>

                                                <div className="mt-1 font-mono text-xs text-slate-400">
                                                    {payment.client
                                                        ?.client_code ||
                                                        '-'}
                                                </div>
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4">
                                                <div className="font-mono font-bold text-slate-700">
                                                    {payment.invoice
                                                        ?.invoice_no ||
                                                        '-'}
                                                </div>
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4">
                                                <span className="text-lg font-bold text-emerald-600">
                                                    {formatMoney(
                                                        payment.amount,
                                                    )}
                                                </span>
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                                {formatDate(
                                                    payment.payment_date,
                                                )}
                                            </td>

                                            <td className="px-5 py-4">
                                                <MethodBadge
                                                    method={
                                                        payment.payment_method
                                                    }
                                                />
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4 font-mono text-sm text-slate-600">
                                                {payment.transaction_id ||
                                                    '-'}
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                                {payment.received_by
                                                    ? `User #${payment.received_by}`
                                                    : '-'}
                                            </td>

                                            <td className="px-5 py-4">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        deletePayment(
                                                            payment,
                                                        )
                                                    }
                                                    className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                                                >
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
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
    description,
    icon,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-3xl font-bold text-slate-800">
                        {value}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        {description}
                    </p>
                </div>

                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-2xl">
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

function MethodBadge({ method }) {
    const normalized = String(
        method || 'Unknown',
    ).toLowerCase();

    let style =
        'bg-slate-100 text-slate-700';

    if (normalized === 'cash') {
        style =
            'bg-emerald-100 text-emerald-700';
    } else if (
        normalized.includes('bank')
    ) {
        style =
            'bg-blue-100 text-blue-700';
    } else if (
        normalized.includes('bkash') ||
        normalized.includes('nagad') ||
        normalized.includes('rocket') ||
        normalized.includes('upay')
    ) {
        style =
            'bg-pink-100 text-pink-700';
    } else if (
        normalized.includes('paypal') ||
        normalized.includes('stripe')
    ) {
        style =
            'bg-violet-100 text-violet-700';
    }

    return (
        <span
            className={`inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold ${style}`}
        >
            {method || 'Unknown'}
        </span>
    );
}

function numberValue(value) {
    const number = Number(value);

    return Number.isFinite(number) ? number : 0;
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

function dateValue(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (!Number.isNaN(date.getTime())) {
        const offset =
            date.getTimezoneOffset() *
            60 *
            1000;

        return new Date(
            date.getTime() - offset,
        )
            .toISOString()
            .slice(0, 10);
    }

    return String(value).slice(0, 10);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 10);
    }

    return date.toLocaleDateString();
}

function localDateString() {
    const date = new Date();

    const offset =
        date.getTimezoneOffset() *
        60 *
        1000;

    return new Date(
        date.getTime() - offset,
    )
        .toISOString()
        .slice(0, 10);
}
