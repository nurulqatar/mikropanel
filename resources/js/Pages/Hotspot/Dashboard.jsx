import AppLayout from '@/Layouts/AppLayout';
import UnifiedFinanceStrip from '@/Components/Finance/UnifiedFinanceStrip';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const dt = (value) =>
    value
        ? new Date(value).toLocaleString()
        : '-';

export default function Dashboard({
    stats = {},
    recentVouchers = [],
    recentSessions = [],
}) {
    const sections = [
        {
            label: 'Servers',
            route: 'hotspot.servers.index',
            description:
                'MikroTik Hotspot servers and sync',
        },
        {
            label: 'Plans',
            route: 'hotspot.plans.index',
            description:
                'Price, validity and speed profiles',
        },
        {
            label: 'Vouchers',
            route: 'hotspot.vouchers.index',
            description:
                'Generate, sell and manage vouchers',
        },
        {
            label: 'Voucher Batches',
            route: 'hotspot.batches.index',
            description:
                'Print and PDF voucher batches',
        },
        {
            label: 'Live Sessions',
            route: 'hotspot.sessions.index',
            description:
                'Current connected Hotspot users',
        },
        {
            label: 'Billing & Dues',
            route: 'hotspot.billing.index',
            description:
                'Outstanding bills and payments',
        },
        {
            label: 'Reports',
            route: 'hotspot.reports.index',
            description:
                'Collection and operator reports',
        },
        {
            label: 'Branding & Portal',
            route: 'hotspot.branding.index',
            description:
                'Voucher and login portal design',
        },
    ];

    return (
        <AppLayout title="Hotspot Dashboard">
            <Head title="Hotspot Dashboard" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black text-slate-900">
                        Hotspot Dashboard
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Hotspot network, vouchers,
                        users and billing at a glance.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                    <Stat
                        label="Servers"
                        value={`${stats.connected_servers ?? 0}/${stats.servers ?? 0}`}
                    />

                    <Stat
                        label="Plans"
                        value={stats.plans}
                    />

                    <Stat
                        label="Unused"
                        value={stats.unused_vouchers}
                    />

                    <Stat
                        label="Active"
                        value={stats.active_vouchers}
                    />

                    <Stat
                        label="Expired"
                        value={stats.expired_vouchers}
                    />

                    <Stat
                        label="Online"
                        value={stats.online_sessions}
                    />

                    <Stat
                        label="Suspended"
                        value={stats.suspended_vouchers}
                    />

                    <Stat
                        label="Today"
                        value={`QAR ${money(
                            stats.today_collection,
                        )}`}
                    />

                    <Stat
                        label="This Month"
                        value={`QAR ${money(
                            stats.month_collection,
                        )}`}
                    />

                    <Stat
                        label="Hotspot Due"
                        value={`QAR ${money(
                            stats.total_due,
                        )}`}
                    />
                </div>

                <UnifiedFinanceStrip />

                <section>
                    <h2 className="mb-4 text-xl font-bold text-slate-900">
                        Hotspot Management
                    </h2>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {sections.map(
                            (section) => (
                                <Link
                                    key={
                                        section.route
                                    }
                                    href={route(
                                        section.route,
                                    )}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md"
                                >
                                    <div className="font-bold text-slate-900">
                                        {
                                            section.label
                                        }
                                    </div>

                                    <div className="mt-1 text-sm text-slate-500">
                                        {
                                            section.description
                                        }
                                    </div>
                                </Link>
                            ),
                        )}
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                        <Header title="Recent Vouchers" />

                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <Th>Voucher</Th>
                                        <Th>Plan</Th>
                                        <Th>Status</Th>
                                        <Th>Expiry</Th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y">
                                    {recentVouchers.map(
                                        (voucher) => (
                                            <tr
                                                key={
                                                    voucher.id
                                                }
                                            >
                                                <Td>
                                                    <Link
                                                        href={route(
                                                            'hotspot.vouchers.show',
                                                            voucher.id,
                                                        )}
                                                        className="font-mono font-bold text-cyan-700"
                                                    >
                                                        {
                                                            voucher.username
                                                        }
                                                    </Link>
                                                </Td>

                                                <Td>
                                                    {voucher.plan?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    <Badge
                                                        value={
                                                            voucher.status
                                                        }
                                                    />
                                                </Td>

                                                <Td>
                                                    {dt(
                                                        voucher.expires_at,
                                                    )}
                                                </Td>
                                            </tr>
                                        ),
                                    )}

                                    {recentVouchers.length ===
                                        0 && (
                                        <Empty
                                            columns={4}
                                            text="No voucher yet."
                                        />
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                        <Header title="Online Users" />

                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <Th>User</Th>
                                        <Th>IP</Th>
                                        <Th>Server</Th>
                                        <Th>Uptime</Th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y">
                                    {recentSessions.map(
                                        (session) => (
                                            <tr
                                                key={
                                                    session.id
                                                }
                                            >
                                                <Td>
                                                    {
                                                        session.username
                                                    }
                                                </Td>

                                                <Td>
                                                    {session.address ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {session.server?.name ||
                                                        '-'}
                                                </Td>

                                                <Td>
                                                    {seconds(
                                                        session.uptime_seconds,
                                                    )}
                                                </Td>
                                            </tr>
                                        ),
                                    )}

                                    {recentSessions.length ===
                                        0 && (
                                        <Empty
                                            columns={4}
                                            text="No user online."
                                        />
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
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
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-black text-slate-900">
                {value ?? 0}
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

function Badge({ value }) {
    return (
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold capitalize text-slate-700">
            {value || '-'}
        </span>
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
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}

function Empty({
    columns,
    text,
}) {
    return (
        <tr>
            <td
                colSpan={columns}
                className="p-8 text-center text-slate-400"
            >
                {text}
            </td>
        </tr>
    );
}

function seconds(value) {
    let total =
        Number(value ?? 0);

    const days =
        Math.floor(
            total / 86400,
        );

    total %= 86400;

    const hours =
        Math.floor(
            total / 3600,
        );

    total %= 3600;

    const minutes =
        Math.floor(
            total / 60,
        );

    return [
        days
            ? `${days}d`
            : null,
        hours
            ? `${hours}h`
            : null,
        `${minutes}m`,
    ]
        .filter(Boolean)
        .join(' ');
}
