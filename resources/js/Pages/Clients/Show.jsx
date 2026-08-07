import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Show({
    client,
    billingSummary = {},
    usageSummary = {},
}) {
    const invoices = client.invoices ?? [];
    const payments = client.payments ?? [];

    const suspendClient = () => {
        if (confirm('Suspend this client?')) {
            router.post(
                route(
                    'clients.suspend',
                    client.id,
                ),
            );
        }
    };

    const activateClient = () => {
        if (confirm('Activate this client?')) {
            router.post(
                route(
                    'clients.unsuspend',
                    client.id,
                ),
            );
        }
    };

    const archiveClient = () => {
        if (
            confirm(
                'Archive this client? Invoice and payment history will be preserved.',
            )
        ) {
            router.delete(
                route(
                    'clients.destroy',
                    client.id,
                ),
            );
        }
    };

    return (
        <AppLayout title="Client Details">
            <Head
                title={`Client - ${client.name}`}
            />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-3xl font-bold text-slate-800">
                                {client.name}
                            </h1>

                            <StatusBadge
                                enabled={client.enabled}
                            />
                        </div>

                        <p className="mt-1 font-mono text-slate-500">
                            {client.client_code}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route(
                                'clients.edit',
                                client.id,
                            )}
                            className="rounded-xl bg-amber-500 px-4 py-2.5 font-semibold text-white hover:bg-amber-600"
                        >
                            Edit Client
                        </Link>

                        <Link
                            href={route(
                                'clients.index',
                            )}
                            className="rounded-xl bg-slate-700 px-4 py-2.5 font-semibold text-white hover:bg-slate-800"
                        >
                            Back
                        </Link>
                    </div>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-bold text-slate-800">
                                Internet Usage
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                {usageSummary.month ||
                                    'Current Month'}{' '}
                                MikroTik Simple Queue usage
                            </p>
                        </div>

                        <div className="text-sm text-slate-500">
                            Last Sync:{' '}
                            <span className="font-semibold text-slate-700">
                                {usageSummary.last_synced_at ||
                                    'Not synced yet'}
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <UsageCard
                            label="Downloaded"
                            bytes={
                                usageSummary.download_bytes
                            }
                            icon="↓"
                            description="Client received data"
                        />

                        <UsageCard
                            label="Uploaded"
                            bytes={
                                usageSummary.upload_bytes
                            }
                            icon="↑"
                            description="Client sent data"
                        />

                        <UsageCard
                            label="Total Usage"
                            bytes={
                                usageSummary.total_bytes
                            }
                            icon="⇅"
                            description="Upload + Download"
                            highlight
                        />

                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <p className="text-sm font-semibold text-slate-500">
                                Connection
                            </p>

                            <p className="mt-3 text-2xl font-bold text-slate-800">
                                {client.connected
                                    ? 'Online'
                                    : 'Offline'}
                            </p>

                            <p className="mt-1 text-xs text-slate-400">
                                Current panel status
                            </p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-3">
                    <InfoPanel title="Client Information">
                        <Info
                            label="Client Code"
                            value={client.client_code}
                            mono
                        />

                        <Info
                            label="Name"
                            value={client.name}
                        />

                        <Info
                            label="Phone"
                            value={client.phone}
                        />

                        <Info
                            label="Email"
                            value={client.email}
                        />

                        <Info
                            label="Address"
                            value={client.address}
                        />
                    </InfoPanel>

                    <InfoPanel title="Network Information">
                        <Info
                            label="IP Address"
                            value={client.ip_address}
                            mono
                        />

                        <Info
                            label="MAC Address"
                            value={client.mac_address}
                            mono
                        />

                        <Info
                            label="Router"
                            value={client.router?.name}
                        />

                        <Info
                            label="IP Pool"
                            value={
                                client.ip_range?.name
                            }
                        />

                        <Info
                            label="Package"
                            value={
                                client.package?.name
                            }
                        />
                    </InfoPanel>

                    <InfoPanel title="Account Information">
                        <Info
                            label="Account Status"
                            value={
                                client.enabled
                                    ? 'Active'
                                    : 'Suspended'
                            }
                        />

                        <Info
                            label="Billing Day"
                            value={
                                client.billing_day
                                    ? `Day ${client.billing_day}`
                                    : '-'
                            }
                        />

                        <Info
                            label="Installed Date"
                            value={formatDate(
                                client.installed_at,
                            )}
                        />

                        <Info
                            label="Expiry Date"
                            value={formatDate(
                                client.expiry_date,
                            )}
                        />

                        <Info
                            label="Connection"
                            value={
                                client.connected
                                    ? 'Online'
                                    : 'Offline'
                            }
                        />
                    </InfoPanel>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-5 text-lg font-bold text-slate-800">
                        MikroTik Resources
                    </h2>

                    <div className="grid gap-4 md:grid-cols-3">
                        <ResourceCard
                            label="DHCP Lease ID"
                            value={
                                client.mikrotik_lease_id
                            }
                        />

                        <ResourceCard
                            label="Static ARP ID"
                            value={
                                client.mikrotik_arp_id
                            }
                        />

                        <ResourceCard
                            label="Simple Queue ID"
                            value={
                                client.mikrotik_queue_id
                            }
                        />
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total Invoices"
                        value={
                            billingSummary.invoice_count ??
                            0
                        }
                    />

                    <SummaryCard
                        label="Total Billed"
                        value={`QAR ${money(
                            billingSummary.total_billed,
                        )}`}
                    />

                    <SummaryCard
                        label="Total Paid"
                        value={`QAR ${money(
                            billingSummary.total_paid,
                        )}`}
                    />

                    <SummaryCard
                        label="Total Due"
                        value={`QAR ${money(
                            billingSummary.total_due,
                        )}`}
                        danger={
                            Number(
                                billingSummary.total_due ??
                                    0,
                            ) > 0
                        }
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-slate-800">
                                Client Invoice Report
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                All invoices belonging to this client
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {invoices.length > 0 && (
                                <>
                                    <a
                                        href={route(
                                            'clients.invoices.print',
                                            client.id,
                                        )}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Print All
                                    </a>

                                    <a
                                        href={route(
                                            'clients.invoices.download',
                                            client.id,
                                        )}
                                        className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                                    >
                                        Download Report PDF
                                    </a>
                                </>
                            )}

                            <Link
                                href={route(
                                    'invoices.create',
                                )}
                                className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700"
                            >
                                Create Invoice
                            </Link>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <TableHead>
                                        Invoice
                                    </TableHead>

                                    <TableHead>
                                        Billing Month
                                    </TableHead>

                                    <TableHead>
                                        Net Amount
                                    </TableHead>

                                    <TableHead>
                                        Paid
                                    </TableHead>

                                    <TableHead>
                                        Due
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
                                {invoices.length > 0 ? (
                                    invoices.map(
                                        (invoice) => {
                                            const netAmount =
                                                Number(
                                                    invoice.amount ??
                                                        0,
                                                ) -
                                                Number(
                                                    invoice.discount ??
                                                        0,
                                                );

                                            return (
                                                <tr
                                                    key={
                                                        invoice.id
                                                    }
                                                    className="hover:bg-slate-50"
                                                >
                                                    <TableCell>
                                                        <div className="font-mono font-bold text-slate-800">
                                                            {
                                                                invoice.invoice_no
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-400">
                                                            {formatDate(
                                                                invoice.issue_date,
                                                            )}
                                                        </div>
                                                    </TableCell>

                                                    <TableCell>
                                                        {formatBillingMonth(
                                                            invoice.billing_month,
                                                        )}
                                                    </TableCell>

                                                    <TableCell>
                                                        QAR{' '}
                                                        {money(
                                                            netAmount,
                                                        )}
                                                    </TableCell>

                                                    <TableCell>
                                                        <span className="font-semibold text-emerald-600">
                                                            QAR{' '}
                                                            {money(
                                                                invoice.paid_amount,
                                                            )}
                                                        </span>
                                                    </TableCell>

                                                    <TableCell>
                                                        <span
                                                            className={
                                                                Number(
                                                                    invoice.due_amount ??
                                                                        0,
                                                                ) >
                                                                0
                                                                    ? 'font-bold text-red-600'
                                                                    : 'text-slate-700'
                                                            }
                                                        >
                                                            QAR{' '}
                                                            {money(
                                                                invoice.due_amount,
                                                            )}
                                                        </span>
                                                    </TableCell>

                                                    <TableCell>
                                                        <InvoiceStatus
                                                            status={
                                                                invoice.status
                                                            }
                                                        />
                                                    </TableCell>

                                                    <TableCell>
                                                        <div className="flex flex-wrap gap-2">
                                                            <a
                                                                href={route(
                                                                    'invoices.print',
                                                                    invoice.id,
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Print
                                                            </a>

                                                            <a
                                                                href={route(
                                                                    'invoices.download',
                                                                    invoice.id,
                                                                )}
                                                                className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700"
                                                            >
                                                                PDF
                                                            </a>

                                                            <Link
                                                                href={route(
                                                                    'invoices.edit',
                                                                    invoice.id,
                                                                )}
                                                                className="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600"
                                                            >
                                                                Edit
                                                            </Link>
                                                        </div>
                                                    </TableCell>
                                                </tr>
                                            );
                                        },
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-6 py-12 text-center text-slate-500"
                                        >
                                            No invoices found for this client.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5">
                        <div>
                            <h2 className="text-lg font-bold text-slate-800">
                                Payment History
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Payments received from this client
                            </p>
                        </div>

                        <Link
                            href={route(
                                'payments.create',
                            )}
                            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            Add Payment
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <TableHead>
                                        Date
                                    </TableHead>

                                    <TableHead>
                                        Invoice
                                    </TableHead>

                                    <TableHead>
                                        Amount
                                    </TableHead>

                                    <TableHead>
                                        Method
                                    </TableHead>

                                    <TableHead>
                                        Transaction ID
                                    </TableHead>

                                    <TableHead>
                                        Notes
                                    </TableHead>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-200">
                                {payments.length > 0 ? (
                                    payments.map(
                                        (payment) => (
                                            <tr
                                                key={
                                                    payment.id
                                                }
                                                className="hover:bg-slate-50"
                                            >
                                                <TableCell>
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </TableCell>

                                                <TableCell>
                                                    {payment
                                                        .invoice
                                                        ?.invoice_no ||
                                                        '-'}
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-bold text-emerald-600">
                                                        QAR{' '}
                                                        {money(
                                                            payment.amount,
                                                        )}
                                                    </span>
                                                </TableCell>

                                                <TableCell>
                                                    {payment.payment_method ||
                                                        '-'}
                                                </TableCell>

                                                <TableCell>
                                                    {payment.transaction_id ||
                                                        '-'}
                                                </TableCell>

                                                <TableCell>
                                                    {payment.notes ||
                                                        '-'}
                                                </TableCell>
                                            </tr>
                                        ),
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-6 py-12 text-center text-slate-500"
                                        >
                                            No payments found for this client.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    {client.enabled ? (
                        <button
                            type="button"
                            onClick={suspendClient}
                            className="rounded-xl bg-red-600 px-5 py-2.5 font-semibold text-white hover:bg-red-700"
                        >
                            Suspend Client
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={activateClient}
                            className="rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white hover:bg-emerald-700"
                        >
                            Activate Client
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={archiveClient}
                        className="rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-black"
                    >
                        Archive Client
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}

function UsageCard({
    label,
    bytes,
    icon,
    description,
    highlight = false,
}) {
    return (
        <div
            className={`rounded-2xl border p-5 ${
                highlight
                    ? 'border-cyan-200 bg-cyan-50'
                    : 'border-slate-200 bg-white'
            }`}
        >
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-3xl font-bold text-slate-800">
                        {formatGigabytes(bytes)}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        {description}
                    </p>
                </div>

                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-xl font-bold text-cyan-700 shadow-sm">
                    {icon}
                </div>
            </div>
        </div>
    );
}

function InfoPanel({ title, children }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="mb-5 text-lg font-bold text-slate-800">
                {title}
            </h2>

            <div className="space-y-4 text-sm">
                {children}
            </div>
        </div>
    );
}

function Info({
    label,
    value,
    mono = false,
}) {
    return (
        <div>
            <p className="text-slate-500">
                {label}
            </p>

            <p
                className={`mt-1 font-semibold text-slate-800 ${
                    mono ? 'font-mono' : ''
                }`}
            >
                {value || '-'}
            </p>
        </div>
    );
}

function ResourceCard({ label, value }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p className="text-sm text-slate-500">
                {label}
            </p>

            <p className="mt-2 break-all font-mono font-semibold text-slate-800">
                {value || 'Not provisioned'}
            </p>
        </div>
    );
}

function SummaryCard({
    label,
    value,
    danger = false,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-medium text-slate-500">
                {label}
            </p>

            <p
                className={`mt-2 text-2xl font-bold ${
                    danger
                        ? 'text-red-600'
                        : 'text-slate-800'
                }`}
            >
                {value}
            </p>
        </div>
    );
}

function StatusBadge({ enabled }) {
    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-bold ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {enabled ? 'Active' : 'Suspended'}
        </span>
    );
}

function InvoiceStatus({ status }) {
    const classes = {
        paid: 'bg-emerald-100 text-emerald-700',
        partial: 'bg-amber-100 text-amber-700',
        overdue: 'bg-red-100 text-red-700',
        unpaid: 'bg-red-100 text-red-700',
        cancelled: 'bg-slate-200 text-slate-700',
    };

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize ${
                classes[status] ||
                'bg-slate-100 text-slate-700'
            }`}
        >
            {status || 'Unknown'}
        </span>
    );
}

function TableHead({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            {children}
        </th>
    );
}

function TableCell({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}

function money(value) {
    return Number(value ?? 0).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

function formatGigabytes(bytes) {
    const value = Number(bytes ?? 0);

    const gigabytes =
        Number.isFinite(value)
            ? value / 1073741824
            : 0;

    return `${gigabytes.toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    )} GB`;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = String(value).slice(0, 10);
    const parts = date.split('-');

    if (parts.length !== 3) {
        return date;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function formatBillingMonth(value) {
    if (!value) {
        return '-';
    }

    const date = String(value).slice(0, 10);
    const [year, month] = date.split('-');

    const months = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    return `${months[Number(month) - 1] || month} ${year}`;
}
