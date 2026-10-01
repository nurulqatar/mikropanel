import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';
import {
    useEffect,
    useRef,
    useState,
} from 'react';

const LIVE_INTERVAL_MS = 500;
const HISTORY_SAMPLES = 600;

export default function HotspotHealth({
    router,
    health: initialHealth,
    importCandidate: initialImportCandidate,
}) {
    const [health, setHealth] =
        useState(initialHealth);

    const [
        importCandidate,
        setImportCandidate,
    ] = useState(
        initialImportCandidate,
    );

    const [live, setLive] =
        useState(null);

    const [rates, setRates] =
        useState({
            wanDownload: 0,
            wanUpload: 0,
            bridgeDownload: 0,
            bridgeUpload: 0,
            perWan: [],
            clients: [],
        });

    const [history, setHistory] =
        useState([]);

    const [message, setMessage] =
        useState(null);

    const [error, setError] =
        useState(null);

    const [busy, setBusy] =
        useState(false);

    const previous =
        useRef(null);

    useEffect(() => {
        let stopped = false;
        let timer = null;

        const poll = async () => {
            if (
                document
                    .visibilityState
                    !== 'visible'
            ) {
                timer =
                    setTimeout(
                        poll,
                        LIVE_INTERVAL_MS,
                    );

                return;
            }

            try {
                const response =
                    await fetch(
                        route(
                            'hotspot.router-health.live',
                            router.id,
                        ),
                        {
                            method:
                                'GET',

                            headers: {
                                Accept:
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            cache:
                                'no-store',
                        },
                    );

                const data =
                    await response.json();

                if (stopped) {
                    return;
                }

                setLive(data);

                if (!data.online) {
                    setError(
                        data.error ||
                            'Live RouterOS data unavailable.',
                    );

                    previous.current =
                        null;

                    return;
                }

                setError(null);

                const old =
                    previous.current;

                if (
                    old?.online
                    && data.timestamp_ms >
                        old.timestamp_ms
                ) {
                    const seconds =
                        Math.max(
                            0.1,
                            (
                                data.timestamp_ms -
                                old.timestamp_ms
                            ) / 1000,
                        );

                    const oldWan =
                        Object.fromEntries(
                            (
                                old.wan_interfaces ||
                                []
                            ).map(
                                (row) => [
                                    row.name,
                                    row,
                                ],
                            ),
                        );

                    let totalRx = 0;
                    let totalTx = 0;

                    const perWan =
                        (
                            data.wan_interfaces ||
                            []
                        ).map(
                            (row) => {
                                const before =
                                    oldWan[
                                        row.name
                                    ];

                                const rx =
                                    before
                                        ? positiveDelta(
                                              row.rx_byte,
                                              before.rx_byte,
                                          )
                                        : 0;

                                const tx =
                                    before
                                        ? positiveDelta(
                                              row.tx_byte,
                                              before.tx_byte,
                                          )
                                        : 0;

                                totalRx +=
                                    rx;

                                totalTx +=
                                    tx;

                                return {
                                    ...row,

                                    download:
                                        bps(
                                            rx,
                                            seconds,
                                        ),

                                    upload:
                                        bps(
                                            tx,
                                            seconds,
                                        ),
                                };
                            },
                        );

                    let bridgeDownload =
                        0;

                    let bridgeUpload =
                        0;

                    if (
                        data.bridge
                        && old.bridge
                        && data.bridge.name
                            === old
                                .bridge
                                .name
                    ) {
                        /*
                         * Bridge TX = router -> clients
                         * Bridge RX = clients -> router
                         */
                        bridgeDownload =
                            bps(
                                positiveDelta(
                                    data
                                        .bridge
                                        .tx_byte,
                                    old
                                        .bridge
                                        .tx_byte,
                                ),
                                seconds,
                            );

                        bridgeUpload =
                            bps(
                                positiveDelta(
                                    data
                                        .bridge
                                        .rx_byte,
                                    old
                                        .bridge
                                        .rx_byte,
                                ),
                                seconds,
                            );
                    }

                    const oldClients =
                        Object.fromEntries(
                            (
                                old.clients ||
                                []
                            ).map(
                                (row) => [
                                    row.key,
                                    row,
                                ],
                            ),
                        );

                    const clients =
                        (
                            data.clients ||
                            []
                        )
                            .map(
                                (row) => {
                                    const before =
                                        oldClients[
                                            row.key
                                        ];

                                    return {
                                        ...row,

                                        download:
                                            before
                                                ? bps(
                                                      positiveDelta(
                                                          row.bytes_out,
                                                          before.bytes_out,
                                                      ),
                                                      seconds,
                                                  )
                                                : 0,

                                        upload:
                                            before
                                                ? bps(
                                                      positiveDelta(
                                                          row.bytes_in,
                                                          before.bytes_in,
                                                      ),
                                                      seconds,
                                                  )
                                                : 0,
                                    };
                                },
                            )
                            .sort(
                                (a, b) =>
                                    b.download +
                                        b.upload -
                                    (
                                        a.download +
                                        a.upload
                                    ),
                            );

                    const wanDownload =
                        bps(
                            totalRx,
                            seconds,
                        );

                    const wanUpload =
                        bps(
                            totalTx,
                            seconds,
                        );

                    setRates({
                        wanDownload,
                        wanUpload,
                        bridgeDownload,
                        bridgeUpload,
                        perWan,
                        clients,
                    });

                    setHistory(
                        (rows) => [
                            ...rows.slice(
                                -(
                                    HISTORY_SAMPLES -
                                    1
                                ),
                            ),

                            {
                                t:
                                    data.timestamp_ms,

                                download:
                                    wanDownload,

                                upload:
                                    wanUpload,
                            },
                        ],
                    );
                }

                previous.current =
                    data;

            } catch (exception) {
                if (!stopped) {
                    setError(
                        exception
                            ?.message ||
                            'Realtime polling failed.',
                    );
                }

            } finally {
                if (!stopped) {
                    /*
                     * JSON/API polling only.
                     * NO page reload.
                     * NO Inertia reload.
                     */
                    timer =
                        setTimeout(
                            poll,
                            LIVE_INTERVAL_MS,
                        );
                }
            }
        };

        poll();

        return () => {
            stopped = true;

            if (timer) {
                clearTimeout(
                    timer,
                );
            }
        };
    }, [router.id]);

    const refreshHealth =
        async () => {
            setBusy(true);
            setError(null);

            try {
                const response =
                    await fetch(
                        route(
                            'hotspot.router-health.snapshot',
                            router.id,
                        ),
                        {
                            headers: {
                                Accept:
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            cache:
                                'no-store',
                        },
                    );

                const data =
                    await response.json();

                setHealth(
                    data.health,
                );

                setImportCandidate(
                    data.importCandidate,
                );

            } catch (exception) {
                setError(
                    exception?.message ||
                        'Health check failed.',
                );

            } finally {
                setBusy(false);
            }
        };

    const postAction =
        async (
            routeName,
        ) => {
            setBusy(true);
            setMessage(null);
            setError(null);

            try {
                const response =
                    await fetch(
                        route(
                            routeName,
                            router.id,
                        ),
                        {
                            method:
                                'POST',

                            credentials:
                                'same-origin',

                            headers: {
                                Accept:
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',

                                'X-CSRF-TOKEN':
                                    csrfToken(),
                            },

                            body:
                                JSON.stringify(
                                    {},
                                ),
                        },
                    );

                const data =
                    await response.json();

                if (
                    !response.ok
                    || data.success ===
                        false
                ) {
                    throw new Error(
                        data.message ||
                            'Operation failed.',
                    );
                }

                setMessage(
                    data.message,
                );

                if (
                    data.health
                ) {
                    setHealth(
                        data.health,
                    );
                }

                if (
                    data.importCandidate
                ) {
                    setImportCandidate(
                        data.importCandidate,
                    );
                }

            } catch (exception) {
                setError(
                    exception?.message ||
                        'Operation failed.',
                );

            } finally {
                setBusy(false);
            }
        };

    const issues =
        health?.issues ?? [];

    return (
        <AppLayout title="Router Health">
            <Head
                title={`${router.name} · Router Health`}
            />

            <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="text-sm font-black uppercase tracking-wide text-cyan-600">
                            Router Health
                        </div>

                        <h1 className="mt-1 text-3xl font-black text-slate-900">
                            {router.name}
                        </h1>

                        <div className="mt-1 text-sm text-slate-500">
                            {router.zone?.name}
                            {' · '}
                            {router.host}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={
                                refreshHealth
                            }
                            disabled={
                                busy
                            }
                            className="rounded-xl border border-slate-300 bg-white px-4 py-2 font-bold text-slate-700 disabled:opacity-50"
                        >
                            {busy
                                ? 'Checking...'
                                : 'Recheck'}
                        </button>

                        <Link
                            href={route(
                                'hotspot.router-health.index',
                            )}
                            className="rounded-xl bg-slate-900 px-4 py-2 font-bold text-white"
                        >
                            All Routers
                        </Link>
                    </div>
                </div>

                {message && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 font-bold text-emerald-800">
                        {message}
                    </div>
                )}

                {error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 font-bold text-red-800">
                        {error}
                    </div>
                )}

                <SmartImport
                    data={
                        importCandidate
                    }
                    busy={
                        busy
                    }
                    onImport={() =>
                        postAction(
                            'hotspot.router-health.import',
                        )
                    }
                />

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-black text-slate-900">
                                Health Card
                            </h2>

                            <div className="mt-1 text-xs text-slate-500">
                                {health?.checked_at ||
                                    '-'}
                            </div>
                        </div>

                        <StatusBadge
                            status={
                                health?.overall
                            }
                        />
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {(
                            health?.checks ||
                            []
                        ).map(
                            (item) => (
                                <HealthItem
                                    key={
                                        item.key
                                    }
                                    item={
                                        item
                                    }
                                />
                            ),
                        )}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-black text-slate-900">
                                Realtime Bandwidth
                            </h2>

                            <p className="mt-1 text-xs font-semibold text-slate-500">
                                0.5 second live update · JSON fetch only · NO PAGE RELOAD
                            </p>
                        </div>

                        <div className="flex items-center gap-2 text-xs font-black">
                            <span
                                className={`h-2.5 w-2.5 rounded-full ${
                                    live?.online
                                        ? 'bg-emerald-500'
                                        : 'bg-red-500'
                                }`}
                            />

                            {live?.online
                                ? 'LIVE'
                                : 'OFFLINE'}
                        </div>
                    </div>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric
                            label="WAN Download"
                            value={formatRate(
                                rates.wanDownload,
                            )}
                        />

                        <Metric
                            label="WAN Upload"
                            value={formatRate(
                                rates.wanUpload,
                            )}
                        />

                        <Metric
                            label="Hotspot Download"
                            value={formatRate(
                                rates.bridgeDownload,
                            )}
                        />

                        <Metric
                            label="Hotspot Upload"
                            value={formatRate(
                                rates.bridgeUpload,
                            )}
                        />
                    </div>

                    <div className="mt-5">
                        <BandwidthChart
                            rows={
                                history
                            }
                        />
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-xl font-black text-slate-900">
                            Multi-WAN Live
                        </h2>

                        <div className="mt-4 space-y-3">
                            {rates.perWan.map(
                                (wan) => (
                                    <div
                                        key={
                                            wan.name
                                        }
                                        className="rounded-xl border border-slate-200 p-4"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-mono font-black">
                                                {
                                                    wan.name
                                                }
                                            </span>

                                            <span
                                                className={`text-xs font-black ${
                                                    wan.running
                                                        ? 'text-emerald-700'
                                                        : 'text-red-600'
                                                }`}
                                            >
                                                {wan.running
                                                    ? 'RUNNING'
                                                    : 'NO LINK'}
                                            </span>
                                        </div>

                                        <div className="mt-3 grid grid-cols-2 gap-3">
                                            <MiniMetric
                                                label="Download"
                                                value={formatRate(
                                                    wan.download,
                                                )}
                                            />

                                            <MiniMetric
                                                label="Upload"
                                                value={formatRate(
                                                    wan.upload,
                                                )}
                                            />
                                        </div>
                                    </div>
                                ),
                            )}

                            {rates.perWan.length ===
                                0 && (
                                <div className="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                                    Waiting for second live sample...
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-xl font-black text-slate-900">
                            DHCP / Pool / IP Bind
                        </h2>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <Metric
                                label="Active Users"
                                value={
                                    live
                                        ?.clients
                                        ?.length ??
                                    health
                                        ?.stats
                                        ?.active_users ??
                                    0
                                }
                            />

                            <Metric
                                label="Bound DHCP"
                                value={
                                    health
                                        ?.stats
                                        ?.bound_leases ??
                                    0
                                }
                            />

                            <Metric
                                label="IP Bindings"
                                value={
                                    health
                                        ?.stats
                                        ?.ip_bindings ??
                                    0
                                }
                            />

                            <Metric
                                label="Bind Conflicts"
                                value={
                                    health
                                        ?.stats
                                        ?.binding_conflicts ??
                                    0
                                }
                            />
                        </div>

                        <div className="mt-5">
                            <div className="flex items-center justify-between text-sm font-black text-slate-700">
                                <span>
                                    DHCP Pool Usage
                                </span>

                                <span>
                                    {health
                                        ?.stats
                                        ?.pool_used ??
                                        0}
                                    {' / '}
                                    {health
                                        ?.stats
                                        ?.pool_total ??
                                        0}
                                    {' · '}
                                    {health
                                        ?.stats
                                        ?.pool_usage_percent ??
                                        0}
                                    %
                                </span>
                            </div>

                            <div className="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full rounded-full bg-cyan-600"
                                    style={{
                                        width: `${Math.min(
                                            100,
                                            Number(
                                                health
                                                    ?.stats
                                                    ?.pool_usage_percent ??
                                                    0,
                                            ),
                                        )}%`,
                                    }}
                                />
                            </div>
                        </div>
                    </section>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-black text-slate-900">
                                Problems
                            </h2>

                            <p className="mt-1 text-xs text-slate-500">
                                Critical / Warning health notifications
                            </p>
                        </div>

                        {(health?.repairable_count ??
                            0) >
                            0 && (
                            <button
                                type="button"
                                disabled={
                                    busy
                                }
                                onClick={() =>
                                    postAction(
                                        'hotspot.router-health.repair',
                                    )
                                }
                                className="rounded-xl bg-amber-500 px-5 py-3 font-black text-white disabled:opacity-50"
                            >
                                {busy
                                    ? 'Repairing...'
                                    : 'Repair Problems'}
                            </button>
                        )}
                    </div>

                    <div className="mt-4 space-y-3">
                        {issues.map(
                            (issue) => (
                                <Issue
                                    key={`${issue.key}-${issue.title}`}
                                    issue={
                                        issue
                                    }
                                />
                            ),
                        )}

                        {issues.length ===
                            0 && (
                            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 font-black text-emerald-800">
                                ✓ No Hotspot health problem detected.
                            </div>
                        )}
                    </div>
                </section>

                {(health
                    ?.binding_conflicts ||
                    []).length >
                    0 && (
                    <section className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                        <h2 className="text-lg font-black text-amber-900">
                            DHCP / IP Bind Conflicts
                        </h2>

                        <div className="mt-4 space-y-2">
                            {health.binding_conflicts.map(
                                (
                                    item,
                                    index,
                                ) => (
                                    <div
                                        key={
                                            index
                                        }
                                        className="rounded-xl bg-white p-3 text-sm"
                                    >
                                        <div className="font-black">
                                            {
                                                item.reason
                                            }
                                        </div>

                                        <div className="mt-1 font-mono text-xs text-slate-500">
                                            IP{' '}
                                            {
                                                item.address
                                            }
                                            {' · '}
                                            Bind{' '}
                                            {
                                                item.binding_mac
                                            }
                                            {' · '}
                                            DHCP{' '}
                                            {
                                                item.lease_mac
                                            }
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </section>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 p-5">
                        <h2 className="text-xl font-black text-slate-900">
                            Live Hotspot Users
                        </h2>

                        <p className="mt-1 text-xs text-slate-500">
                            Per-user download/upload from RouterOS byte counter delta.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        User
                                    </th>

                                    <th className="px-4 py-3">
                                        IP
                                    </th>

                                    <th className="px-4 py-3">
                                        MAC
                                    </th>

                                    <th className="px-4 py-3">
                                        Download
                                    </th>

                                    <th className="px-4 py-3">
                                        Upload
                                    </th>

                                    <th className="px-4 py-3">
                                        Uptime
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {rates.clients
                                    .slice(
                                        0,
                                        100,
                                    )
                                    .map(
                                        (client) => (
                                            <tr
                                                key={
                                                    client.key
                                                }
                                                className="border-t border-slate-100"
                                            >
                                                <td className="px-4 py-3 font-black">
                                                    {
                                                        client.user
                                                    }
                                                </td>

                                                <td className="px-4 py-3 font-mono">
                                                    {
                                                        client.address
                                                    }
                                                </td>

                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {
                                                        client.mac_address
                                                    }
                                                </td>

                                                <td className="px-4 py-3 font-black text-emerald-700">
                                                    {formatRate(
                                                        client.download,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 font-black text-cyan-700">
                                                    {formatRate(
                                                        client.upload,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {
                                                        client.uptime
                                                    }
                                                </td>
                                            </tr>
                                        ),
                                    )}

                                {rates.clients.length ===
                                    0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-8 text-center text-slate-400"
                                        >
                                            No active users or waiting for live counters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function SmartImport({
    data,
    busy,
    onImport,
}) {
    if (!data?.found) {
        return null;
    }

    return (
        <section
            className={`rounded-2xl border p-5 ${
                data.already_imported
                    ? 'border-emerald-200 bg-emerald-50'
                    : data.can_import
                      ? 'border-cyan-200 bg-cyan-50'
                      : 'border-amber-200 bg-amber-50'
            }`}
        >
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-black text-slate-900">
                        {data.already_imported
                            ? '✓ Existing Hotspot Managed'
                            : 'Existing Hotspot Found'}
                    </h2>

                    <div className="mt-1 text-sm text-slate-600">
                        {data.reason}
                    </div>

                    {data.candidate && (
                        <div className="mt-3 font-mono text-xs text-slate-500">
                            {
                                data
                                    .candidate
                                    .hotspot_server
                            }
                            {' · '}
                            {
                                data
                                    .candidate
                                    .interface
                            }
                            {' · '}
                            {
                                data
                                    .candidate
                                    .gateway_cidr
                            }
                            {' · '}
                            {
                                data
                                    .candidate
                                    .pool_name
                            }
                        </div>
                    )}
                </div>

                {data.can_import && (
                    <button
                        type="button"
                        disabled={
                            busy
                        }
                        onClick={
                            onImport
                        }
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-black text-white disabled:opacity-50"
                    >
                        Import & Manage
                    </button>
                )}
            </div>
        </section>
    );
}

function HealthItem({
    item,
}) {
    const unknown =
        item.status === null;

    return (
        <div
            className={`rounded-xl border p-4 ${
                unknown
                    ? 'border-slate-200 bg-slate-50'
                    : item.status
                      ? 'border-emerald-200 bg-emerald-50'
                      : 'border-red-200 bg-red-50'
            }`}
        >
            <div className="flex items-center justify-between">
                <span className="font-black text-slate-800">
                    {item.label}
                </span>

                <span
                    className={`text-lg font-black ${
                        unknown
                            ? 'text-slate-400'
                            : item.status
                              ? 'text-emerald-600'
                              : 'text-red-600'
                    }`}
                >
                    {unknown
                        ? '—'
                        : item.status
                          ? '✓'
                          : '✕'}
                </span>
            </div>

            <div className="mt-2 break-words text-xs text-slate-500">
                {item.detail}
            </div>
        </div>
    );
}

function StatusBadge({
    status,
}) {
    const healthy =
        status === 'healthy';

    const critical =
        status === 'critical';

    return (
        <span
            className={`rounded-full px-4 py-2 text-xs font-black ${
                healthy
                    ? 'bg-emerald-100 text-emerald-700'
                    : critical
                      ? 'bg-red-100 text-red-700'
                      : 'bg-amber-100 text-amber-700'
            }`}
        >
            {healthy
                ? 'HEALTHY'
                : critical
                  ? 'CRITICAL'
                  : 'WARNING'}
        </span>
    );
}

function Metric({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-xs font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black text-slate-900">
                {value}
            </div>
        </div>
    );
}

function MiniMetric({
    label,
    value,
}) {
    return (
        <div className="rounded-lg bg-slate-50 p-3">
            <div className="text-xs text-slate-400">
                {label}
            </div>

            <div className="mt-1 font-black">
                {value}
            </div>
        </div>
    );
}

function Issue({
    issue,
}) {
    const critical =
        issue.severity ===
        'critical';

    return (
        <div
            className={`rounded-xl border p-4 ${
                critical
                    ? 'border-red-200 bg-red-50'
                    : 'border-amber-200 bg-amber-50'
            }`}
        >
            <div className="flex items-center justify-between gap-3">
                <div
                    className={`font-black ${
                        critical
                            ? 'text-red-800'
                            : 'text-amber-800'
                    }`}
                >
                    {issue.title}
                </div>

                {issue.repairable && (
                    <span className="rounded-full bg-white px-2 py-1 text-[10px] font-black">
                        AUTO REPAIR
                    </span>
                )}
            </div>

            <div className="mt-1 text-sm text-slate-600">
                {issue.message}
            </div>
        </div>
    );
}

function BandwidthChart({
    rows,
}) {
    const width = 1000;
    const height = 220;
    const pad = 22;

    const samples =
        rows.slice(
            -HISTORY_SAMPLES,
        );

    const max =
        Math.max(
            1,
            ...samples.map(
                (row) =>
                    Math.max(
                        row.download,
                        row.upload,
                    ),
            ),
        );

    const points =
        (field) =>
            samples
                .map(
                    (
                        row,
                        index,
                    ) => {
                        const x =
                            samples.length <=
                            1
                                ? pad
                                : pad +
                                  (
                                      index /
                                      (
                                          samples.length -
                                          1
                                      )
                                  ) *
                                      (
                                          width -
                                          pad *
                                              2
                                      );

                        const y =
                            height -
                            pad -
                            (
                                row[field] /
                                max
                            ) *
                                (
                                    height -
                                    pad *
                                        2
                                );

                        return `${x},${y}`;
                    },
                )
                .join(' ');

    return (
        <div className="rounded-xl bg-slate-950 p-4">
            <div className="mb-3 flex items-center justify-between text-xs font-black text-slate-200">
                <span>
                    Last ~5 Minutes
                </span>

                <span>
                    Peak{' '}
                    {formatRate(
                        max,
                    )}
                </span>
            </div>

            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-56 w-full"
                preserveAspectRatio="none"
            >
                <line
                    x1={pad}
                    y1={height - pad}
                    x2={width - pad}
                    y2={height - pad}
                    className="stroke-slate-700"
                    strokeWidth="2"
                />

                <polyline
                    points={points(
                        'download',
                    )}
                    fill="none"
                    className="stroke-emerald-400"
                    strokeWidth="4"
                />

                <polyline
                    points={points(
                        'upload',
                    )}
                    fill="none"
                    className="stroke-cyan-400"
                    strokeWidth="4"
                />
            </svg>

            <div className="mt-2 flex gap-5 text-xs font-bold text-slate-300">
                <span>
                    Download
                </span>

                <span>
                    Upload
                </span>
            </div>
        </div>
    );
}

function positiveDelta(
    current,
    old,
) {
    const value =
        Number(current || 0) -
        Number(old || 0);

    return value >= 0
        ? value
        : 0;
}

function bps(
    bytes,
    seconds,
) {
    return seconds > 0
        ? Number(bytes || 0) *
              8 /
              seconds
        : 0;
}

function formatRate(
    value,
) {
    const number =
        Number(value || 0);

    if (
        number >=
        1_000_000_000
    ) {
        return `${(
            number /
            1_000_000_000
        ).toFixed(2)} Gbps`;
    }

    if (
        number >=
        1_000_000
    ) {
        return `${(
            number /
            1_000_000
        ).toFixed(2)} Mbps`;
    }

    if (
        number >=
        1000
    ) {
        return `${(
            number /
            1000
        ).toFixed(1)} Kbps`;
    }

    return `${number.toFixed(
        0,
    )} bps`;
}

function csrfToken() {
    return (
        document
            .querySelector(
                'meta[name="csrf-token"]',
            )
            ?.getAttribute(
                'content',
            ) || ''
    );
}
