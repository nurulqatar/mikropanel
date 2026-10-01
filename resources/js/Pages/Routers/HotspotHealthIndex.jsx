import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function HotspotHealthIndex({
    routers = [],
    alertSummary = {},
    alerts = [],
}) {
    return (
        <AppLayout title="Router Health">
            <Head title="Router Health" />

            <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
                <div>
                    <div className="text-sm font-black uppercase tracking-wide text-cyan-600">
                        Hotspot Monitoring
                    </div>

                    <h1 className="mt-1 text-3xl font-black text-slate-900">
                        Router Health
                    </h1>

                    <p className="mt-2 text-sm text-slate-500">
                        Persistent problem notifications,
                        realtime bandwidth, DHCP/IP Bind
                        monitoring and safe repair.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="Active Alerts"
                        value={
                            alertSummary.total ??
                            0
                        }
                        tone="slate"
                    />

                    <SummaryCard
                        label="Critical"
                        value={
                            alertSummary.critical ??
                            0
                        }
                        tone="red"
                    />

                    <SummaryCard
                        label="Warning"
                        value={
                            alertSummary.warning ??
                            0
                        }
                        tone="amber"
                    />
                </div>

                {alerts.length > 0 ? (
                    <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-xl font-black text-slate-900">
                                        Problem Notifications
                                    </h2>

                                    <p className="mt-1 text-xs text-slate-500">
                                        Alerts remain active
                                        until a later health
                                        scan confirms recovery.
                                    </p>
                                </div>

                                <span className="rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-700">
                                    {
                                        alertSummary.total
                                    }{' '}
                                    ACTIVE
                                </span>
                            </div>
                        </div>

                        <div className="divide-y divide-slate-100">
                            {alerts.map(
                                (alert) => (
                                    <Link
                                        key={
                                            alert.id
                                        }
                                        href={route(
                                            'hotspot.router-health.show',
                                            alert.router_id,
                                        )}
                                        className="block p-4 transition hover:bg-slate-50"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <AlertBadge
                                                        severity={
                                                            alert.severity
                                                        }
                                                    />

                                                    <span className="font-black text-slate-900">
                                                        {
                                                            alert.router_name
                                                        }
                                                    </span>

                                                    <span className="text-slate-300">
                                                        ·
                                                    </span>

                                                    <span className="font-bold text-slate-700">
                                                        {
                                                            alert.title
                                                        }
                                                    </span>
                                                </div>

                                                <p className="mt-2 text-sm text-slate-600">
                                                    {alert.message ||
                                                        'Router health problem detected.'}
                                                </p>

                                                <p className="mt-2 text-xs text-slate-400">
                                                    Last
                                                    seen:{' '}
                                                    {dateText(
                                                        alert.last_seen_at,
                                                    )}
                                                    {' · '}
                                                    {
                                                        alert.occurrences
                                                    }{' '}
                                                    scan(s)
                                                </p>
                                            </div>

                                            {alert.repairable && (
                                                <span className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">
                                                    REPAIRABLE
                                                </span>
                                            )}
                                        </div>
                                    </Link>
                                ),
                            )}
                        </div>
                    </section>
                ) : (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 font-black text-emerald-800">
                        ✓ No active Router Health
                        notification.
                    </div>
                )}

                <section>
                    <div className="mb-4">
                        <h2 className="text-xl font-black text-slate-900">
                            Hotspot Routers
                        </h2>

                        <p className="mt-1 text-xs text-slate-500">
                            Open a router for 0.5-second
                            realtime traffic and detailed
                            health.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {routers.map(
                            (router) => (
                                <div
                                    key={
                                        router.id
                                    }
                                    className={`rounded-2xl border bg-white p-5 shadow-sm ${
                                        router.critical_count >
                                        0
                                            ? 'border-red-300'
                                            : router.warning_count >
                                                0
                                              ? 'border-amber-300'
                                              : 'border-slate-200'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 className="text-lg font-black text-slate-900">
                                                {
                                                    router.name
                                                }
                                            </h3>

                                            <div className="mt-1 text-sm text-slate-500">
                                                {
                                                    router
                                                        .zone
                                                        ?.name
                                                }
                                            </div>

                                            <div className="mt-1 font-mono text-xs text-slate-400">
                                                {
                                                    router.host
                                                }
                                            </div>
                                        </div>

                                        <div className="flex flex-col items-end gap-2">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-black ${
                                                    router.connected
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-600'
                                                }`}
                                            >
                                                {router.connected
                                                    ? 'ONLINE'
                                                    : 'CHECK'}
                                            </span>

                                            {router.alert_count >
                                                0 && (
                                                <span
                                                    className={`rounded-full px-3 py-1 text-xs font-black ${
                                                        router.critical_count >
                                                        0
                                                            ? 'bg-red-100 text-red-700'
                                                            : 'bg-amber-100 text-amber-700'
                                                    }`}
                                                >
                                                    {
                                                        router.alert_count
                                                    }{' '}
                                                    ALERT
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {(router.critical_count >
                                        0 ||
                                        router.warning_count >
                                            0) && (
                                        <div className="mt-4 flex flex-wrap gap-2 text-xs font-bold">
                                            {router.critical_count >
                                                0 && (
                                                <span className="rounded-lg bg-red-50 px-2 py-1 text-red-700">
                                                    Critical{' '}
                                                    {
                                                        router.critical_count
                                                    }
                                                </span>
                                            )}

                                            {router.warning_count >
                                                0 && (
                                                <span className="rounded-lg bg-amber-50 px-2 py-1 text-amber-700">
                                                    Warning{' '}
                                                    {
                                                        router.warning_count
                                                    }
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    <div className="mt-5">
                                        <Link
                                            href={route(
                                                'hotspot.router-health.show',
                                                router.id,
                                            )}
                                            className="block w-full rounded-xl bg-slate-900 px-4 py-3 text-center font-black text-white hover:bg-slate-800"
                                        >
                                            Open Health
                                        </Link>
                                    </div>
                                </div>
                            ),
                        )}

                        {routers.length ===
                            0 && (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">
                                No Hotspot router is
                                available for this
                                account.
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    label,
    value,
    tone,
}) {
    const classes = {
        red:
            'border-red-200 bg-red-50 text-red-800',

        amber:
            'border-amber-200 bg-amber-50 text-amber-800',

        slate:
            'border-slate-200 bg-white text-slate-900',
    };

    return (
        <div
            className={`rounded-2xl border p-5 shadow-sm ${classes[tone]}`}
        >
            <div className="text-xs font-black uppercase tracking-wide opacity-60">
                {label}
            </div>

            <div className="mt-2 text-3xl font-black">
                {value}
            </div>
        </div>
    );
}

function AlertBadge({
    severity,
}) {
    return (
        <span
            className={`rounded-full px-2.5 py-1 text-[10px] font-black ${
                severity === 'critical'
                    ? 'bg-red-100 text-red-700'
                    : 'bg-amber-100 text-amber-700'
            }`}
        >
            {severity === 'critical'
                ? 'CRITICAL'
                : 'WARNING'}
        </span>
    );
}

function dateText(value) {
    if (!value) {
        return '-';
    }

    try {
        return new Intl.DateTimeFormat(
            undefined,
            {
                dateStyle: 'medium',
                timeStyle: 'short',
            },
        ).format(
            new Date(value),
        );

    } catch {
        return value;
    }
}
