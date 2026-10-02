import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
} from '@inertiajs/react';

const money = (value) =>
    Number(value || 0).toFixed(2);

export default function Commercial({
    hotels = [],
    invoices = [],
    stats = {},
}) {
    const pay = (invoice) => {
        const amount =
            window.prompt(
                `Payment amount. Due: ${invoice.due_amount}`,
                invoice.due_amount,
            );

        if (!amount) {
            return;
        }

        const method =
            window.prompt(
                'Payment method',
                'Cash',
            ) || 'Cash';

        router.post(
            route(
                'superadmin.hotel.commercial.payment',
                invoice.id,
            ),
            {
                amount,
                payment_method:
                    method,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const renew = (hotel) => {
        if (
            !window.confirm(
                `Renew ${hotel.name}?`,
            )
        ) {
            return;
        }

        router.post(
            route(
                'superadmin.hotel.commercial.renew',
                hotel.id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <SuperAdminLayout title="Hotel Commercial">
            <Head title="Hotel Commercial" />

            <div className="space-y-6">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="text-xs font-black uppercase tracking-[0.25em] text-cyan-300">
                        Hotel Hotspot SaaS
                    </div>

                    <h1 className="mt-2 text-3xl font-black">
                        Commercial Operations
                    </h1>
                </section>

                <div className="grid gap-4 md:grid-cols-4">
                    <Stat
                        label="Hotels"
                        value={
                            stats.hotels
                        }
                    />
                    <Stat
                        label="Active"
                        value={
                            stats.active_hotels
                        }
                    />
                    <Stat
                        label="Collected"
                        value={money(
                            stats.collected,
                        )}
                    />
                    <Stat
                        label="Outstanding"
                        value={money(
                            stats.due,
                        )}
                    />
                </div>

                <Panel title="Hotel Subscriptions">
                    <Table
                        headers={[
                            'Hotel',
                            'Status',
                            'Subscription Expiry',
                            'Action',
                        ]}
                    >
                        {hotels.map(
                            (hotel) => (
                                <tr
                                    key={
                                        hotel.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        <b>
                                            {
                                                hotel.name
                                            }
                                        </b>
                                    </Td>

                                    <Td>
                                        {
                                            hotel.status
                                        }
                                    </Td>

                                    <Td>
                                        {hotel
                                            .active_subscription
                                            ?.expires_at
                                            ? new Date(
                                                hotel
                                                    .active_subscription
                                                    .expires_at,
                                            ).toLocaleString()
                                            : '—'}
                                    </Td>

                                    <Td>
                                        <button
                                            onClick={() =>
                                                renew(
                                                    hotel,
                                                )
                                            }
                                            className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white"
                                        >
                                            Renew
                                        </button>
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>

                <Panel title="Hotel Invoices">
                    <Table
                        headers={[
                            'Invoice',
                            'Hotel',
                            'Amount',
                            'Paid',
                            'Due',
                            'Status',
                            'Action',
                        ]}
                    >
                        {invoices.map(
                            (invoice) => (
                                <tr
                                    key={
                                        invoice.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        {
                                            invoice.invoice_no
                                        }
                                    </Td>

                                    <Td>
                                        {invoice.hotel?.name
                                            ?? '—'}
                                    </Td>

                                    <Td>
                                        {invoice.currency}{' '}
                                        {money(
                                            invoice.amount,
                                        )}
                                    </Td>

                                    <Td>
                                        {money(
                                            invoice.paid_amount,
                                        )}
                                    </Td>

                                    <Td>
                                        {money(
                                            invoice.due_amount,
                                        )}
                                    </Td>

                                    <Td>
                                        {
                                            invoice.status
                                        }
                                    </Td>

                                    <Td>
                                        {Number(
                                            invoice.due_amount,
                                        ) > 0 && (
                                            <button
                                                onClick={() =>
                                                    pay(
                                                        invoice,
                                                    )
                                                }
                                                className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-black text-white"
                                            >
                                                Receive Payment
                                            </button>
                                        )}
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>
            </div>
        </SuperAdminLayout>
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
                {value ?? 0}
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
