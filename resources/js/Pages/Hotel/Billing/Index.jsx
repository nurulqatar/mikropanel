import HotelLayout from '@/Layouts/HotelLayout';
import { Head } from '@inertiajs/react';

const money = (value) =>
    Number(value || 0).toFixed(2);

const date = (value) =>
    value
        ? new Date(value).toLocaleDateString()
        : '—';

export default function Index({
    invoices = [],
    payments = [],
    stats = {},
}) {
    return (
        <HotelLayout title="Hotel Billing">
            <Head title="Hotel Billing" />

            <div className="space-y-6">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="text-xs font-black uppercase tracking-[0.25em] text-cyan-300">
                        Subscription Billing
                    </div>

                    <h1 className="mt-2 text-3xl font-black">
                        Hotel Billing & Payments
                    </h1>
                </section>

                <div className="grid gap-4 md:grid-cols-4">
                    <Stat
                        label="Invoiced"
                        value={money(
                            stats.total,
                        )}
                    />

                    <Stat
                        label="Paid"
                        value={money(
                            stats.paid,
                        )}
                    />

                    <Stat
                        label="Due"
                        value={money(
                            stats.due,
                        )}
                    />

                    <Stat
                        label="Overdue"
                        value={
                            stats.overdue
                            ?? 0
                        }
                    />
                </div>

                <Panel title="Invoices">
                    <Table
                        headers={[
                            'Invoice',
                            'Issue',
                            'Due',
                            'Amount',
                            'Paid',
                            'Balance',
                            'Status',
                        ]}
                    >
                        {invoices.map(
                            (row) => (
                                <tr
                                    key={
                                        row.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        {
                                            row.invoice_no
                                        }
                                    </Td>
                                    <Td>
                                        {date(
                                            row.issue_date,
                                        )}
                                    </Td>
                                    <Td>
                                        {date(
                                            row.due_date,
                                        )}
                                    </Td>
                                    <Td>
                                        {row.currency}{' '}
                                        {money(
                                            row.amount,
                                        )}
                                    </Td>
                                    <Td>
                                        {money(
                                            row.paid_amount,
                                        )}
                                    </Td>
                                    <Td>
                                        {money(
                                            row.due_amount,
                                        )}
                                    </Td>
                                    <Td>
                                        {
                                            row.status
                                        }
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>

                <Panel title="Payment History">
                    <Table
                        headers={[
                            'Date',
                            'Amount',
                            'Method',
                            'Reference',
                        ]}
                    >
                        {payments.map(
                            (row) => (
                                <tr
                                    key={
                                        row.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        {date(
                                            row.payment_date,
                                        )}
                                    </Td>
                                    <Td>
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
                                        {row.reference
                                            ?? '—'}
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>
            </div>
        </HotelLayout>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border bg-white p-5">
            <div className="text-xs font-black uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black">
                {value}
            </div>
        </div>
    );
}

function Panel({
    title,
    children,
}) {
    return (
        <section className="overflow-hidden rounded-2xl border bg-white">
            <div className="border-b px-5 py-4 text-lg font-black">
                {title}
            </div>

            {children}
        </section>
    );
}

function Table({
    headers,
    children,
}) {
    const rows =
        Array.isArray(children)
            ? children
            : children
              ? [children]
              : [];

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full">
                <thead className="bg-slate-50">
                    <tr>
                        {headers.map(
                            (header) => (
                                <th
                                    key={
                                        header
                                    }
                                    className="px-4 py-3 text-left text-xs font-black uppercase text-slate-500"
                                >
                                    {
                                        header
                                    }
                                </th>
                            ),
                        )}
                    </tr>
                </thead>

                <tbody>
                    {rows.length ? (
                        children
                    ) : (
                        <tr>
                            <td
                                colSpan={
                                    headers.length
                                }
                                className="p-8 text-center text-slate-400"
                            >
                                No records.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function Td({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-4 py-4 text-sm">
            {children}
        </td>
    );
}
