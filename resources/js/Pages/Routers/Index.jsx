import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';
import { useState } from 'react';

export default function Index({
    routers,
    flash = {},
}) {
    const [working, setWorking] = useState(null);

    const runAction = (action, id) => {
        const key = `${action}-${id}`;

        setWorking(key);

        router.post(
            route(`routers.${action}`, id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setWorking(null),
            },
        );
    };

    const deleteRouter = (id) => {
        if (
            !confirm(
                'Are you sure you want to delete this router?',
            )
        ) {
            return;
        }

        router.delete(
            route('routers.destroy', id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="MikroTik Routers">
            <Head title="MikroTik Routers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            MikroTik Routers
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Live API monitoring, ping and
                            MikroTik synchronization
                        </p>
                    </div>

                    <Link
                        href={route('routers.create')}
                        className="rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white shadow hover:bg-cyan-700"
                    >
                        + Add Router
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                        {flash.error}
                    </div>
                )}

                {routers.length === 0 ? (
                    <div className="rounded-xl bg-white p-10 text-center shadow">
                        <div className="text-5xl">
                            📡
                        </div>

                        <h2 className="mt-4 text-xl font-bold text-slate-800">
                            No Router Found
                        </h2>

                        <p className="mt-2 text-slate-500">
                            Add your first MikroTik router.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {routers.map((item) => (
                            <RouterCard
                                key={item.id}
                                item={item}
                                working={working}
                                runAction={runAction}
                                deleteRouter={deleteRouter}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function RouterCard({
    item,
    working,
    runAction,
    deleteRouter,
}) {
    const live = item.live ?? {};
    const online = Boolean(live.success);
    const disabled = Boolean(live.disabled);

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 p-6">
                <div className="flex items-start gap-4">
                    <div
                        className={`flex h-14 w-14 items-center justify-center rounded-xl text-2xl ${
                            online
                                ? 'bg-emerald-100'
                                : 'bg-red-100'
                        }`}
                    >
                        📡
                    </div>

                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-2xl font-bold text-slate-800">
                                {live.identity ||
                                    item.identity ||
                                    item.name}
                            </h2>

                            <StatusBadge
                                online={online}
                                disabled={disabled}
                            />
                        </div>

                        <p className="mt-1 font-mono text-sm text-slate-500">
                            {item.host}:{item.api_port}
                            {item.use_ssl
                                ? ' • SSL'
                                : ' • API'}
                        </p>

                        <p className="mt-1 text-xs text-slate-500">
                            Client Interface:{' '}
                            <span className="font-mono">
                                {item.client_interface || '-'}
                            </span>
                            {' • '}
                            DHCP Server:{' '}
                            <span className="font-mono">
                                {item.dhcp_server || '-'}
                            </span>
                        </p>

                        <p
                            className={`mt-2 text-sm ${
                                online
                                    ? 'text-emerald-600'
                                    : 'text-red-600'
                            }`}
                        >
                            {live.message ||
                                item.last_error ||
                                'Status unavailable'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() =>
                            runAction('ping', item.id)
                        }
                        disabled={
                            working === `ping-${item.id}`
                        }
                        className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    >
                        {working === `ping-${item.id}`
                            ? 'Pinging...'
                            : 'Ping'}
                    </button>

                    <button
                        type="button"
                        onClick={() =>
                            runAction('sync', item.id)
                        }
                        disabled={
                            working === `sync-${item.id}`
                        }
                        className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                    >
                        {working === `sync-${item.id}`
                            ? 'Syncing...'
                            : 'MikroTik Sync'}
                    </button>

                    <Link
                        href={route(
                            'routers.edit',
                            item.id,
                        )}
                        className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                    >
                        Edit
                    </Link>

                    <button
                        type="button"
                        onClick={() =>
                            deleteRouter(item.id)
                        }
                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <div className="grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4">
                <InfoCard
                    label="RouterOS Version"
                    value={
                        live.version ||
                        item.routeros_version ||
                        '-'
                    }
                />

                <InfoCard
                    label="Board"
                    value={
                        live.board_name ||
                        item.board_name ||
                        '-'
                    }
                />

                <InfoCard
                    label="Uptime"
                    value={
                        live.uptime ||
                        item.uptime ||
                        '-'
                    }
                />

                <InfoCard
                    label="API Latency"
                    value={
                        live.latency_ms !== null &&
                        live.latency_ms !== undefined
                            ? `${live.latency_ms} ms`
                            : '-'
                    }
                />

                <InfoCard
                    label="CPU Load"
                    value={
                        live.cpu_load !== null &&
                        live.cpu_load !== undefined
                            ? `${live.cpu_load}%`
                            : item.cpu_load !== null &&
                                item.cpu_load !== undefined
                              ? `${item.cpu_load}%`
                              : '-'
                    }
                />

                <InfoCard
                    label="Memory"
                    value={`${formatBytes(
                        live.free_memory ??
                            item.free_memory,
                    )} free / ${formatBytes(
                        live.total_memory ??
                            item.total_memory,
                    )}`}
                />

                <InfoCard
                    label="Architecture"
                    value={
                        live.architecture ||
                        live.platform ||
                        '-'
                    }
                />

                <InfoCard
                    label="Last Checked"
                    value={formatDate(
                        live.checked_at ||
                            item.last_checked_at,
                    )}
                />
            </div>

            <div className="grid border-t border-slate-200 bg-slate-50 sm:grid-cols-3">
                <CountBox
                    label="DHCP Leases"
                    value={
                        live.dhcp_leases_count ??
                        item.dhcp_leases_count ??
                        0
                    }
                />

                <CountBox
                    label="ARP Entries"
                    value={
                        live.arp_entries_count ??
                        item.arp_entries_count ??
                        0
                    }
                />

                <CountBox
                    label="Simple Queues"
                    value={
                        live.simple_queues_count ??
                        item.simple_queues_count ??
                        0
                    }
                />
            </div>
        </section>
    );
}

function StatusBadge({ online, disabled }) {
    if (disabled) {
        return (
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                Disabled
            </span>
        );
    }

    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-bold ${
                online
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {online ? '● Online' : '● Offline'}
        </span>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className="mt-2 break-words font-bold text-slate-800">
                {value}
            </p>
        </div>
    );
}

function CountBox({ label, value }) {
    return (
        <div className="border-b border-slate-200 p-5 text-center last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0">
            <p className="text-2xl font-bold text-slate-800">
                {value}
            </p>

            <p className="mt-1 text-sm text-slate-500">
                {label}
            </p>
        </div>
    );
}

function formatBytes(value) {
    const bytes = Number(value);

    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '-';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const unitIndex = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );

    const amount =
        bytes / Math.pow(1024, unitIndex);

    return `${amount.toFixed(
        unitIndex === 0 ? 0 : 1,
    )} ${units[unitIndex]}`;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
}
