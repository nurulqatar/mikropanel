import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({
    ranges = [],
    flash = {},
}) {
    const enabledCount = ranges.filter(
        (range) => Boolean(range.enabled),
    ).length;

    const routerCount = new Set(
        ranges
            .map((range) => range.router_id)
            .filter(Boolean),
    ).size;

    const totalCapacity = ranges.reduce(
        (total, range) =>
            total +
            calculateCapacity(
                range.start_ip,
                range.end_ip,
            ),
        0,
    );

    const deleteRange = (range) => {
        if (
            !confirm(
                `Delete IP Pool "${range.name}"?`,
            )
        ) {
            return;
        }

        router.delete(
            route('ip-ranges.destroy', range.id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="IP Pools">
            <Head title="IP Pools" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            IP Pools
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Manage client IP ranges for your MikroTik routers
                        </p>
                    </div>

                    <Link
                        href={route('ip-ranges.create')}
                        className="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-cyan-700"
                    >
                        <span className="text-lg">+</span>
                        Add IP Pool
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                        {flash.error}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total Pools"
                        value={ranges.length}
                        description="Configured IP ranges"
                        icon="🌐"
                    />

                    <SummaryCard
                        label="Enabled Pools"
                        value={enabledCount}
                        description="Available for clients"
                        icon="✅"
                    />

                    <SummaryCard
                        label="Routers"
                        value={routerCount}
                        description="Routers with IP pools"
                        icon="📡"
                    />

                    <SummaryCard
                        label="Total Capacity"
                        value={totalCapacity.toLocaleString()}
                        description="IP addresses in all pools"
                        icon="🔢"
                    />
                </div>

                {ranges.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <div className="text-6xl">
                            🌐
                        </div>

                        <h2 className="mt-5 text-2xl font-bold text-slate-800">
                            No IP Pool Found
                        </h2>

                        <p className="mx-auto mt-2 max-w-md text-slate-500">
                            Create an IP pool to automatically assign free IP addresses to new clients.
                        </p>

                        <Link
                            href={route('ip-ranges.create')}
                            className="mt-6 inline-flex rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700"
                        >
                            Create First IP Pool
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h2 className="text-lg font-bold text-slate-800">
                                Configured IP Pools
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Free IP addresses are allocated automatically from these ranges.
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>
                                            Pool
                                        </TableHead>

                                        <TableHead>
                                            Router
                                        </TableHead>

                                        <TableHead>
                                            Network
                                        </TableHead>

                                        <TableHead>
                                            Address Range
                                        </TableHead>

                                        <TableHead>
                                            Gateway
                                        </TableHead>

                                        <TableHead>
                                            Capacity
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
                                    {ranges.map((range) => {
                                        const capacity =
                                            calculateCapacity(
                                                range.start_ip,
                                                range.end_ip,
                                            );

                                        return (
                                            <tr
                                                key={range.id}
                                                className="transition hover:bg-slate-50"
                                            >
                                                <td className="px-5 py-4">
                                                    <div className="font-bold text-slate-800">
                                                        {range.name}
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
                                                        Interface:{' '}
                                                        <span className="font-mono">
                                                            {range.interface ||
                                                                '-'}
                                                        </span>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-700">
                                                        {range.router?.name ||
                                                            'Unknown Router'}
                                                    </div>
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 font-mono text-sm text-slate-700">
                                                    {range.network}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="whitespace-nowrap rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm text-slate-700">
                                                        {range.start_ip}
                                                        <span className="mx-2 text-slate-400">
                                                            →
                                                        </span>
                                                        {range.end_ip}
                                                    </div>
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-4 font-mono text-sm text-slate-700">
                                                    {range.gateway ||
                                                        '-'}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span className="rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-700">
                                                        {capacity.toLocaleString()}{' '}
                                                        IPs
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <StatusBadge
                                                        enabled={Boolean(
                                                            range.enabled,
                                                        )}
                                                    />
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="flex flex-wrap gap-2">
                                                        <Link
                                                            href={route(
                                                                'ip-ranges.edit',
                                                                range.id,
                                                            )}
                                                            className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-amber-600"
                                                        >
                                                            Edit
                                                        </Link>

                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                deleteRange(
                                                                    range,
                                                                )
                                                            }
                                                            className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
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

function StatusBadge({ enabled }) {
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {enabled ? '● Enabled' : '● Disabled'}
        </span>
    );
}

function calculateCapacity(startIp, endIp) {
    const start = ipv4ToNumber(startIp);
    const end = ipv4ToNumber(endIp);

    if (
        start === null ||
        end === null ||
        end < start
    ) {
        return 0;
    }

    return end - start + 1;
}

function ipv4ToNumber(ip) {
    if (!ip) {
        return null;
    }

    const parts = String(ip)
        .trim()
        .split('.')
        .map(Number);

    if (
        parts.length !== 4 ||
        parts.some(
            (part) =>
                !Number.isInteger(part) ||
                part < 0 ||
                part > 255,
        )
    ) {
        return null;
    }

    return parts.reduce(
        (total, part) => total * 256 + part,
        0,
    );
}
