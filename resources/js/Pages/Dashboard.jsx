import AppLayout from '@/Layouts/AppLayout';
import ClientQuickDesk from '@/Components/Dashboard/ClientQuickDesk';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Dashboard({
    clientStats = {},
    systemStats = {},
    financialStats = {},
    invoiceStats = {},
    expiryStats = {},
    attention = {},
    routerHealth = [],
    ipPools = [],
    chartData = [],
    expiringClients = [],
    overdueInvoices = [],
    recentClients = [],
    recentPayments = [],
    quickDesk = {},
    flash = {},
}) {
    const maxChartValue = Math.max(
        1,
        ...chartData.flatMap((item) => [
            numberValue(item.collection),
            numberValue(item.expense),
        ]),
    );

    const attentionItems = [
        {
            label: 'Active Clients Offline',
            description:
                'Active accounts without a current connection',
            count: attention.offline_clients ?? 0,
            href: route('clients.index'),
            danger:
                Number(
                    attention.offline_clients ?? 0,
                ) > 0,
        },
        {
            label: 'Routers Offline',
            description:
                'MikroTik routers currently unavailable',
            count: attention.offline_routers ?? 0,
            href: route('routers.index'),
            danger:
                Number(
                    attention.offline_routers ?? 0,
                ) > 0,
        },
        {
            label: 'Expiring Within 3 Days',
            description:
                'Clients requiring renewal attention',
            count: attention.expiring_soon ?? 0,
            href: route('clients.index'),
            danger:
                Number(
                    attention.expiring_soon ?? 0,
                ) > 0,
        },
        {
            label: 'Overdue Invoices',
            description:
                'Invoices past their due date',
            count:
                attention.overdue_invoices ?? 0,
            href: route('invoices.index'),
            danger:
                Number(
                    attention.overdue_invoices ??
                        0,
                ) > 0,
        },
        {
            label: 'IP Pools Nearly Full',
            description:
                'Pools with 85% usage or low free IPs',
            count:
                attention.near_full_pools ?? 0,
            href: route('ip-ranges.index'),
            danger:
                Number(
                    attention.near_full_pools ??
                        0,
                ) > 0,
        },
    ];

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-7">
                <section className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p className="text-sm font-bold uppercase tracking-wider text-cyan-600">
                            MikroPanel
                        </p>

                        <h1 className="mt-1 text-3xl font-bold text-slate-900">
                            ISP Management Dashboard
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Clients, collections, expenses and network health at a glance
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route(
                                'clients.create',
                            )}
                            className="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-cyan-700"
                        >
                            + Add Client
                        </Link>

                        <Link
                            href={route(
                                'payments.create',
                            )}
                            className="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700"
                        >
                            Receive Payment
                        </Link>

                        <Link
                            href={route(
                                'invoices.create',
                            )}
                            className="rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-violet-700"
                        >
                            Create Invoice
                        </Link>

                        <Link
                            href={route(
                                'expenses.create',
                            )}
                            className="rounded-xl bg-slate-700 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-slate-800"
                        >
                            Add Expense
                        </Link>
                    </div>
                </section>

                <ClientQuickDesk
                    quickDesk={quickDesk}
                    flash={flash}
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    <ClientCard
                        label="Total Clients"
                        value={clientStats.total}
                        description="All registered clients"
                        icon="👥"
                    />

                    <ClientCard
                        label="Active"
                        value={clientStats.active}
                        description="Enabled accounts"
                        icon="✓"
                        tone="green"
                    />

                    <ClientCard
                        label="Online"
                        value={clientStats.online}
                        description="Currently connected"
                        icon="●"
                        tone="green"
                    />

                    <ClientCard
                        label="Offline"
                        value={clientStats.offline}
                        description="Active but disconnected"
                        icon="●"
                        tone="gray"
                    />

                    <ClientCard
                        label="Suspended"
                        value={clientStats.suspended}
                        description="Disabled accounts"
                        icon="!"
                        tone="red"
                    />

                    <ClientCard
                        label="Expired"
                        value={clientStats.expired}
                        description="Past expiry date"
                        icon="⌛"
                        tone="amber"
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <FinanceCard
                        label="Monthly Collection"
                        value={`QAR ${money(
                            financialStats.monthly_collection,
                        )}`}
                        description={`Today: QAR ${money(
                            financialStats.today_collection,
                        )}`}
                        icon="↗"
                        tone="green"
                    />

                    <FinanceCard
                        label="Monthly Expenses"
                        value={`QAR ${money(
                            financialStats.monthly_expenses,
                        )}`}
                        description={`Today: QAR ${money(
                            financialStats.today_expenses,
                        )}`}
                        icon="↘"
                        tone="red"
                    />

                    <FinanceCard
                        label="Net Profit"
                        value={`QAR ${money(
                            financialStats.net_profit,
                        )}`}
                        description="Collection minus expenses"
                        icon="="
                        tone={
                            numberValue(
                                financialStats.net_profit,
                            ) >= 0
                                ? 'cyan'
                                : 'red'
                        }
                    />

                    <FinanceCard
                        label="Total Due"
                        value={`QAR ${money(
                            financialStats.total_due,
                        )}`}
                        description={`Overdue: QAR ${money(
                            financialStats.overdue_amount,
                        )}`}
                        icon="!"
                        tone="amber"
                    />
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">
                                    Needs Attention
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Important items requiring action
                                </p>
                            </div>

                            <div className="rounded-full bg-red-50 px-4 py-2 text-sm font-bold text-red-700">
                                {attentionItems.reduce(
                                    (total, item) =>
                                        total +
                                        Number(
                                            item.count ?? 0,
                                        ),
                                    0,
                                )}{' '}
                                Alerts
                            </div>
                        </div>
                    </div>

                    <div className="grid divide-y divide-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0 xl:grid-cols-5">
                        {attentionItems.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className="p-5 transition hover:bg-slate-50"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-bold text-slate-800">
                                            {item.label}
                                        </p>

                                        <p className="mt-1 text-xs leading-5 text-slate-500">
                                            {item.description}
                                        </p>
                                    </div>

                                    <span
                                        className={`flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-lg font-bold ${
                                            item.danger
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-emerald-100 text-emerald-700'
                                        }`}
                                    >
                                        {item.count ?? 0}
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">
                                    Last 7 Days Finance
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Daily collection compared with expenses
                                </p>
                            </div>

                            <div className="flex gap-4 text-xs font-semibold">
                                <span className="flex items-center gap-2 text-emerald-700">
                                    <span className="h-3 w-3 rounded bg-emerald-500" />
                                    Collection
                                </span>

                                <span className="flex items-center gap-2 text-red-700">
                                    <span className="h-3 w-3 rounded bg-red-500" />
                                    Expense
                                </span>
                            </div>
                        </div>

                        <div className="mt-7 space-y-5">
                            {chartData.map((item) => (
                                <div
                                    key={item.date}
                                    className="grid items-center gap-3 sm:grid-cols-[55px_1fr_110px]"
                                >
                                    <div>
                                        <p className="font-bold text-slate-700">
                                            {item.label}
                                        </p>

                                        <p className="text-xs text-slate-400">
                                            {shortDate(
                                                item.date,
                                            )}
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <ChartBar
                                            value={
                                                item.collection
                                            }
                                            max={
                                                maxChartValue
                                            }
                                            tone="green"
                                        />

                                        <ChartBar
                                            value={
                                                item.expense
                                            }
                                            max={
                                                maxChartValue
                                            }
                                            tone="red"
                                        />
                                    </div>

                                    <div className="text-right text-xs">
                                        <p className="font-bold text-emerald-600">
                                            +{money(
                                                item.collection,
                                            )}
                                        </p>

                                        <p className="mt-1 font-bold text-red-600">
                                            -{money(
                                                item.expense,
                                            )}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-bold text-slate-900">
                            Billing Alerts
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Current invoice and expiry status
                        </p>

                        <div className="mt-6 space-y-4">
                            <MiniStat
                                label="Unpaid Invoices"
                                value={
                                    invoiceStats.unpaid
                                }
                                tone="red"
                            />

                            <MiniStat
                                label="Partial Invoices"
                                value={
                                    invoiceStats.partial
                                }
                                tone="amber"
                            />

                            <MiniStat
                                label="Overdue Invoices"
                                value={
                                    invoiceStats.overdue
                                }
                                tone="red"
                            />

                            <MiniStat
                                label="Expire Today"
                                value={expiryStats.today}
                                tone="amber"
                            />

                            <MiniStat
                                label="Expire Tomorrow"
                                value={
                                    expiryStats.tomorrow
                                }
                                tone="cyan"
                            />

                            <MiniStat
                                label="Within 3 Days"
                                value={
                                    expiryStats.within_three_days
                                }
                                tone="violet"
                            />
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-bold text-slate-900">
                                Router Health
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Stored MikroTik monitoring information
                            </p>
                        </div>

                        <Link
                            href={route('routers.index')}
                            className="text-sm font-bold text-cyan-700 hover:underline"
                        >
                            Manage Routers →
                        </Link>
                    </div>

                    {routerHealth.length === 0 ? (
                        <EmptyState text="No router configured." />
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                            {routerHealth.map((router) => (
                                <RouterCard
                                    key={router.id}
                                    router={router}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <section>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-bold text-slate-900">
                                IP Pool Usage
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Used and available client IP addresses
                            </p>
                        </div>

                        <Link
                            href={route(
                                'ip-ranges.index',
                            )}
                            className="text-sm font-bold text-cyan-700 hover:underline"
                        >
                            Manage IP Pools →
                        </Link>
                    </div>

                    {ipPools.length === 0 ? (
                        <EmptyState text="No IP pool configured." />
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                            {ipPools.map((pool) => (
                                <PoolCard
                                    key={pool.id}
                                    pool={pool}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <DataPanel
                        title="Expiring Clients"
                        description="Clients expiring within three days"
                        linkText="All Clients"
                        linkHref={route(
                            'clients.index',
                        )}
                    >
                        {expiringClients.length === 0 ? (
                            <EmptyTableRow
                                columns={4}
                                text="No clients expiring soon."
                            />
                        ) : (
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>
                                            Client
                                        </TableHead>

                                        <TableHead>
                                            Package
                                        </TableHead>

                                        <TableHead>
                                            Expiry
                                        </TableHead>

                                        <TableHead>
                                            Status
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {expiringClients.map(
                                        (client) => (
                                            <tr
                                                key={
                                                    client.id
                                                }
                                            >
                                                <TableCell>
                                                    <Link
                                                        href={route(
                                                            'clients.show',
                                                            client.id,
                                                        )}
                                                        className="font-bold text-cyan-700 hover:underline"
                                                    >
                                                        {
                                                            client.name
                                                        }
                                                    </Link>

                                                    <p className="mt-1 font-mono text-xs text-slate-400">
                                                        {
                                                            client.client_code
                                                        }
                                                    </p>
                                                </TableCell>

                                                <TableCell>
                                                    {client.package ||
                                                        '-'}
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-bold text-amber-600">
                                                        {formatDate(
                                                            client.expiry_date,
                                                        )}
                                                    </span>
                                                </TableCell>

                                                <TableCell>
                                                    <ConnectionBadge
                                                        connected={
                                                            client.connected
                                                        }
                                                    />
                                                </TableCell>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        )}
                    </DataPanel>

                    <DataPanel
                        title="Overdue Invoices"
                        description="Invoices past their due date"
                        linkText="All Invoices"
                        linkHref={route(
                            'invoices.index',
                        )}
                    >
                        {overdueInvoices.length === 0 ? (
                            <EmptyTableRow
                                columns={4}
                                text="No overdue invoices."
                            />
                        ) : (
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
                                            Due Date
                                        </TableHead>

                                        <TableHead>
                                            Due
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {overdueInvoices.map(
                                        (invoice) => (
                                            <tr
                                                key={
                                                    invoice.id
                                                }
                                            >
                                                <TableCell>
                                                    <Link
                                                        href={route(
                                                            'invoices.edit',
                                                            invoice.id,
                                                        )}
                                                        className="font-mono font-bold text-cyan-700 hover:underline"
                                                    >
                                                        {
                                                            invoice.invoice_no
                                                        }
                                                    </Link>
                                                </TableCell>

                                                <TableCell>
                                                    {invoice.client
                                                        ?.name ||
                                                        '-'}
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-bold text-red-600">
                                                        {formatDate(
                                                            invoice.due_date,
                                                        )}
                                                    </span>
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-bold text-red-600">
                                                        QAR{' '}
                                                        {money(
                                                            invoice.due_amount,
                                                        )}
                                                    </span>
                                                </TableCell>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        )}
                    </DataPanel>
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <DataPanel
                        title="Recent Clients"
                        description="Latest registered clients"
                        linkText="All Clients"
                        linkHref={route(
                            'clients.index',
                        )}
                    >
                        {recentClients.length === 0 ? (
                            <EmptyTableRow
                                columns={4}
                                text="No clients found."
                            />
                        ) : (
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>
                                            Client
                                        </TableHead>

                                        <TableHead>
                                            IP
                                        </TableHead>

                                        <TableHead>
                                            Account
                                        </TableHead>

                                        <TableHead>
                                            Connection
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {recentClients.map(
                                        (client) => (
                                            <tr
                                                key={
                                                    client.id
                                                }
                                            >
                                                <TableCell>
                                                    <Link
                                                        href={route(
                                                            'clients.show',
                                                            client.id,
                                                        )}
                                                        className="font-bold text-cyan-700 hover:underline"
                                                    >
                                                        {
                                                            client.name
                                                        }
                                                    </Link>

                                                    <p className="mt-1 font-mono text-xs text-slate-400">
                                                        {
                                                            client.client_code
                                                        }
                                                    </p>
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-mono">
                                                        {client.ip_address ||
                                                            '-'}
                                                    </span>
                                                </TableCell>

                                                <TableCell>
                                                    <AccountBadge
                                                        enabled={
                                                            client.enabled
                                                        }
                                                    />
                                                </TableCell>

                                                <TableCell>
                                                    <ConnectionBadge
                                                        connected={
                                                            client.connected
                                                        }
                                                    />
                                                </TableCell>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        )}
                    </DataPanel>

                    <DataPanel
                        title="Recent Payments"
                        description="Latest received payments"
                        linkText="All Payments"
                        linkHref={route(
                            'payments.index',
                        )}
                    >
                        {recentPayments.length === 0 ? (
                            <EmptyTableRow
                                columns={4}
                                text="No payments found."
                            />
                        ) : (
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
                                            Date
                                        </TableHead>

                                        <TableHead>
                                            Amount
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {recentPayments.map(
                                        (payment) => (
                                            <tr
                                                key={
                                                    payment.id
                                                }
                                            >
                                                <TableCell>
                                                    {payment.client
                                                        ?.name ||
                                                        '-'}
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-mono">
                                                        {payment.invoice
                                                            ?.invoice_no ||
                                                            '-'}
                                                    </span>
                                                </TableCell>

                                                <TableCell>
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </TableCell>

                                                <TableCell>
                                                    <span className="font-bold text-emerald-600">
                                                        QAR{' '}
                                                        {money(
                                                            payment.amount,
                                                        )}
                                                    </span>
                                                </TableCell>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        )}
                    </DataPanel>
                </section>

                <section className="grid gap-4 sm:grid-cols-3">
                    <SystemCard
                        label="Routers"
                        value={systemStats.routers}
                        href={route(
                            'routers.index',
                        )}
                    />

                    <SystemCard
                        label="Packages"
                        value={systemStats.packages}
                        href={route(
                            'packages.index',
                        )}
                    />

                    <SystemCard
                        label="IP Pools"
                        value={systemStats.ip_pools}
                        href={route(
                            'ip-ranges.index',
                        )}
                    />
                </section>
            </div>
        </AppLayout>
    );
}

function ClientCard({
    label,
    value,
    description,
    icon,
    tone = 'cyan',
}) {
    const tones = {
        cyan: 'bg-cyan-50 text-cyan-700',
        green: 'bg-emerald-50 text-emerald-700',
        red: 'bg-red-50 text-red-700',
        amber: 'bg-amber-50 text-amber-700',
        gray: 'bg-slate-100 text-slate-600',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-3xl font-bold text-slate-900">
                        {value ?? 0}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        {description}
                    </p>
                </div>

                <div
                    className={`flex h-11 w-11 items-center justify-center rounded-xl text-lg font-black ${tones[tone]}`}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}

function FinanceCard({
    label,
    value,
    description,
    icon,
    tone,
}) {
    const tones = {
        green: 'bg-emerald-100 text-emerald-700',
        red: 'bg-red-100 text-red-700',
        amber: 'bg-amber-100 text-amber-700',
        cyan: 'bg-cyan-100 text-cyan-700',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-2xl font-bold text-slate-900">
                        {value}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        {description}
                    </p>
                </div>

                <div
                    className={`flex h-11 w-11 items-center justify-center rounded-xl text-xl font-black ${tones[tone]}`}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}

function MiniStat({ label, value, tone }) {
    const tones = {
        red: 'bg-red-50 text-red-700',
        amber: 'bg-amber-50 text-amber-700',
        cyan: 'bg-cyan-50 text-cyan-700',
        violet: 'bg-violet-50 text-violet-700',
    };

    return (
        <div className="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
            <p className="font-semibold text-slate-700">
                {label}
            </p>

            <span
                className={`rounded-full px-3 py-1 text-sm font-bold ${tones[tone]}`}
            >
                {value ?? 0}
            </span>
        </div>
    );
}

function ChartBar({ value, max, tone }) {
    const percentage = Math.max(
        numberValue(value) > 0 ? 2 : 0,
        Math.min(
            100,
            (numberValue(value) /
                numberValue(max)) *
                100,
        ),
    );

    return (
        <div className="h-3 overflow-hidden rounded-full bg-slate-100">
            <div
                className={`h-full rounded-full ${
                    tone === 'green'
                        ? 'bg-emerald-500'
                        : 'bg-red-500'
                }`}
                style={{
                    width: `${percentage}%`,
                }}
            />
        </div>
    );
}

function RouterCard({ router }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <Link
                        href={route(
                            'routers.edit',
                            router.id,
                        )}
                        className="text-lg font-bold text-slate-900 hover:text-cyan-700"
                    >
                        {router.name}
                    </Link>

                    <p className="mt-1 font-mono text-sm text-slate-500">
                        {router.host}
                    </p>
                </div>

                <ConnectionBadge
                    connected={router.connected}
                />
            </div>

            <div className="mt-5 grid grid-cols-2 gap-3 text-sm">
                <RouterInfo
                    label="Identity"
                    value={
                        router.identity || '-'
                    }
                />

                <RouterInfo
                    label="RouterOS"
                    value={router.version || '-'}
                />

                <RouterInfo
                    label="CPU Load"
                    value={
                        router.cpu_load !== null &&
                        router.cpu_load !==
                            undefined
                            ? `${router.cpu_load}%`
                            : '-'
                    }
                />

                <RouterInfo
                    label="Memory"
                    value={
                        router.memory_percentage !==
                            null &&
                        router.memory_percentage !==
                            undefined
                            ? `${router.memory_percentage}%`
                            : '-'
                    }
                />

                <RouterInfo
                    label="DHCP Leases"
                    value={router.dhcp_count ?? 0}
                />

                <RouterInfo
                    label="Queues"
                    value={router.queue_count ?? 0}
                />

                <RouterInfo
                    label="ARP Entries"
                    value={router.arp_count ?? 0}
                />

                <RouterInfo
                    label="Uptime"
                    value={router.uptime || '-'}
                />
            </div>

            <p className="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-400">
                Last checked:{' '}
                {formatDateTime(
                    router.last_checked_at,
                )}
            </p>

            {router.last_error && (
                <p className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                    {router.last_error}
                </p>
            )}
        </div>
    );
}

function RouterInfo({ label, value }) {
    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <p className="text-xs text-slate-400">
                {label}
            </p>

            <p className="mt-1 truncate font-bold text-slate-700">
                {value}
            </p>
        </div>
    );
}

function PoolCard({ pool }) {
    const barTone = pool.near_full
        ? 'bg-red-500'
        : pool.percentage >= 60
          ? 'bg-amber-500'
          : 'bg-emerald-500';

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <Link
                        href={route(
                            'ip-ranges.edit',
                            pool.id,
                        )}
                        className="text-lg font-bold text-slate-900 hover:text-cyan-700"
                    >
                        {pool.name}
                    </Link>

                    <p className="mt-1 text-sm text-slate-500">
                        {pool.router || '-'}
                    </p>
                </div>

                <span
                    className={`rounded-full px-3 py-1 text-xs font-bold ${
                        pool.near_full
                            ? 'bg-red-100 text-red-700'
                            : 'bg-emerald-100 text-emerald-700'
                    }`}
                >
                    {pool.percentage}%
                </span>
            </div>

            <p className="mt-4 font-mono text-xs text-slate-500">
                {pool.start_ip} → {pool.end_ip}
            </p>

            <div className="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                <div
                    className={`h-full rounded-full ${barTone}`}
                    style={{
                        width: `${Math.min(
                            100,
                            pool.percentage,
                        )}%`,
                    }}
                />
            </div>

            <div className="mt-4 grid grid-cols-3 gap-2 text-center">
                <PoolNumber
                    label="Total"
                    value={pool.capacity}
                />

                <PoolNumber
                    label="Used"
                    value={pool.used}
                />

                <PoolNumber
                    label="Free"
                    value={pool.free}
                />
            </div>
        </div>
    );
}

function PoolNumber({ label, value }) {
    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <p className="text-xs text-slate-400">
                {label}
            </p>

            <p className="mt-1 font-bold text-slate-800">
                {Number(
                    value ?? 0,
                ).toLocaleString()}
            </p>
        </div>
    );
}

function DataPanel({
    title,
    description,
    linkText,
    linkHref,
    children,
}) {
    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 className="text-lg font-bold text-slate-900">
                        {title}
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        {description}
                    </p>
                </div>

                <Link
                    href={linkHref}
                    className="whitespace-nowrap text-sm font-bold text-cyan-700 hover:underline"
                >
                    {linkText} →
                </Link>
            </div>

            <div className="overflow-x-auto">
                {children}
            </div>
        </div>
    );
}

function SystemCard({ label, value, href }) {
    return (
        <Link
            href={href}
            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-300"
        >
            <p className="text-sm font-semibold text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-3xl font-bold text-slate-900">
                {value ?? 0}
            </p>
        </Link>
    );
}

function AccountBadge({ enabled }) {
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {enabled ? 'Active' : 'Suspended'}
        </span>
    );
}

function ConnectionBadge({ connected }) {
    return (
        <span
            className={`inline-flex items-center gap-2 whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold ${
                connected
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-slate-100 text-slate-600'
            }`}
        >
            <span
                className={`h-2 w-2 rounded-full ${
                    connected
                        ? 'bg-emerald-500'
                        : 'bg-slate-400'
                }`}
            />

            {connected ? 'Online' : 'Offline'}
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

function EmptyTableRow({ columns, text }) {
    return (
        <table className="min-w-full">
            <tbody>
                <tr>
                    <td
                        colSpan={columns}
                        className="px-6 py-12 text-center text-slate-500"
                    >
                        {text}
                    </td>
                </tr>
            </tbody>
        </table>
    );
}

function EmptyState({ text }) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            {text}
        </div>
    );
}

function numberValue(value) {
    const number = Number(value);

    return Number.isFinite(number)
        ? number
        : 0;
}

function money(value) {
    return numberValue(value).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = String(value).slice(0, 10);
    const [year, month, day] =
        date.split('-');

    return day && month && year
        ? `${day}/${month}/${year}`
        : date;
}

function shortDate(value) {
    if (!value) {
        return '-';
    }

    const [, month, day] = String(value)
        .slice(0, 10)
        .split('-');

    return day && month
        ? `${day}/${month}`
        : value;
}

function formatDateTime(value) {
    if (!value) {
        return 'Never';
    }

    return String(value).replace(
        'T',
        ' ',
    ).slice(0, 19);
}
