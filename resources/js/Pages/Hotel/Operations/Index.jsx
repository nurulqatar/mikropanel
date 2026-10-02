import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

const bytes = (value) =>
    Number(value || 0).toLocaleString();

const dateTime = (value) =>
    value
        ? new Date(value).toLocaleString()
        : '—';

export default function Index({
    stats = {},
    routers = [],
    vouchers = [],
    notifications = [],
    audits = [],
    can_manage_vouchers = false,
}) {
    const post = (
        name,
        params = {},
        data = {},
    ) => {
        router.post(
            route(
                name,
                params,
            ),
            data,
            {
                preserveScroll: true,
            },
        );
    };

    const extend = (voucher) => {
        const value =
            window.prompt(
                'New checkout date (YYYY-MM-DD)',
            );

        if (!value) {
            return;
        }

        post(
            'hotel.operations.voucher.extend',
            voucher.id,
            {
                checkout_date:
                    value,
            },
        );
    };

    return (
        <HotelLayout title="Hotel WiFi Operations">
            <Head title="Hotel WiFi Operations" />

            <div className="space-y-6">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="text-xs font-black uppercase tracking-[0.25em] text-cyan-300">
                        Commercial Operations
                    </div>

                    <h1 className="mt-2 text-3xl font-black">
                        Hotel WiFi Operations Center
                    </h1>

                    <p className="mt-2 text-sm text-slate-300">
                        Live users, router health, vouchers,
                        alerts and audit activity.
                    </p>
                </section>

                <div className="grid gap-4 md:grid-cols-5">
                    <Stat
                        label="Online"
                        value={
                            stats.online_users
                        }
                    />

                    <Stat
                        label="Active Vouchers"
                        value={
                            stats.active_vouchers
                        }
                    />

                    <Stat
                        label="Router Alerts"
                        value={
                            stats.offline_routers
                        }
                    />

                    <Stat
                        label="Unread Alerts"
                        value={
                            stats.unread_alerts
                        }
                    />

                    <Stat
                        label="Billing Due"
                        value={
                            Number(
                                stats.billing_due
                                ?? 0,
                            ).toFixed(2)
                        }
                    />
                </div>

                <Panel title="Router Health">
                    <Table
                        headers={[
                            'Router',
                            'Status',
                            'Last Test',
                            'Error',
                        ]}
                    >
                        {routers.map(
                            (row) => (
                                <tr
                                    key={
                                        row.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        <b>
                                            {
                                                row.name
                                            }
                                        </b>
                                        <div className="text-xs text-slate-400">
                                            {
                                                row.host
                                            }
                                        </div>
                                    </Td>

                                    <Td>
                                        {row.enabled
                                            ? row.status
                                            : 'disabled'}
                                    </Td>

                                    <Td>
                                        {dateTime(
                                            row.last_tested_at,
                                        )}
                                    </Td>

                                    <Td>
                                        {row.last_error
                                            ?? '—'}
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>

                <Panel title="Voucher Control">
                    <Table
                        headers={[
                            'Voucher',
                            'Guest',
                            'Room',
                            'Status',
                            'Expiry',
                            'Usage',
                            'Actions',
                        ]}
                    >
                        {vouchers.map(
                            (row) => (
                                <tr
                                    key={
                                        row.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        <span className="font-mono font-black">
                                            {
                                                row.username
                                            }
                                        </span>
                                    </Td>

                                    <Td>
                                        {row.stay?.guest?.name
                                            ?? '—'}
                                    </Td>

                                    <Td>
                                        {row.stay?.room_number
                                            ?? '—'}
                                    </Td>

                                    <Td>
                                        {
                                            row.status
                                        }
                                    </Td>

                                    <Td>
                                        {dateTime(
                                            row.expires_at,
                                        )}
                                    </Td>

                                    <Td>
                                        {bytes(
                                            Number(
                                                row.bytes_in
                                                ?? 0,
                                            ) +
                                                Number(
                                                    row.bytes_out
                                                    ?? 0,
                                                ),
                                        )}
                                    </Td>

                                    <Td>
                                        {can_manage_vouchers ? (
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    onClick={() =>
                                                        post(
                                                            'hotel.operations.voucher.disconnect',
                                                            row.id,
                                                        )
                                                    }
                                                    className="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-black text-white"
                                                >
                                                    Disconnect
                                                </button>

                                                <button
                                                    onClick={() =>
                                                        extend(
                                                            row,
                                                        )
                                                    }
                                                    className="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-black text-white"
                                                >
                                                    Extend
                                                </button>

                                                {row.status ===
                                                'revoked' ? (
                                                    <button
                                                        onClick={() =>
                                                            post(
                                                                'hotel.operations.voucher.reactivate',
                                                                row.id,
                                                            )
                                                        }
                                                        className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-black text-white"
                                                    >
                                                        Reactivate
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    'Revoke this voucher?',
                                                                )
                                                            ) {
                                                                post(
                                                                    'hotel.operations.voucher.revoke',
                                                                    row.id,
                                                                );
                                                            }
                                                        }}
                                                        className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-black text-white"
                                                    >
                                                        Revoke
                                                    </button>
                                                )}
                                            </div>
                                        ) : (
                                            'View only'
                                        )}
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>

                <Panel title="Hotel Alerts">
                    <Table
                        headers={[
                            'Severity',
                            'Title',
                            'Message',
                            'Time',
                            'Action',
                        ]}
                    >
                        {notifications.map(
                            (row) => (
                                <tr
                                    key={
                                        row.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        {
                                            row.severity
                                        }
                                    </Td>

                                    <Td>
                                        {
                                            row.title
                                        }
                                    </Td>

                                    <Td>
                                        {
                                            row.message
                                        }
                                    </Td>

                                    <Td>
                                        {dateTime(
                                            row.created_at,
                                        )}
                                    </Td>

                                    <Td>
                                        {!row.read_at && (
                                            <button
                                                onClick={() =>
                                                    post(
                                                        'hotel.operations.notifications.read',
                                                        row.id,
                                                    )
                                                }
                                                className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-black text-white"
                                            >
                                                Mark Read
                                            </button>
                                        )}
                                    </Td>
                                </tr>
                            ),
                        )}
                    </Table>
                </Panel>

                <Panel
                    title={
                        <div className="flex items-center justify-between gap-4">
                            <span>
                                Audit Log
                            </span>

                            <Link
                                href={route(
                                    'hotel.operations.audit.csv',
                                )}
                                className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-black text-white"
                            >
                                Export CSV
                            </Link>
                        </div>
                    }
                >
                    <Table
                        headers={[
                            'Time',
                            'Actor',
                            'Action',
                            'Description',
                            'IP',
                        ]}
                    >
                        {audits.map(
                            (row) => (
                                <tr
                                    key={
                                        row.id
                                    }
                                    className="border-t"
                                >
                                    <Td>
                                        {dateTime(
                                            row.created_at,
                                        )}
                                    </Td>

                                    <Td>
                                        {row.actor_name
                                            ?? row.actor_type}
                                    </Td>

                                    <Td>
                                        {
                                            row.action
                                        }
                                    </Td>

                                    <Td>
                                        {row.description
                                            ?? '—'}
                                    </Td>

                                    <Td>
                                        {row.ip_address
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
        <div className="rounded-2xl border bg-white p-5 shadow-sm">
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
        <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
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
                    {rows.length > 0 ? (
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
        <td className="whitespace-nowrap px-4 py-4 text-sm align-top">
            {children}
        </td>
    );
}
