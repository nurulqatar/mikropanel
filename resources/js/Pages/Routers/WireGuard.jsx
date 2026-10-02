import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router as inertiaRouter,
} from '@inertiajs/react';
import { useState } from 'react';

export default function WireGuard({
    mikrotik,
    peer,
    status,
    command,
    server,
    flash = {},
}) {
    const [working, setWorking] =
        useState(null);

    const [copied, setCopied] =
        useState(false);

    const post = (name) => {
        setWorking(name);

        inertiaRouter.post(
            route(
                `routers.wireguard.${name}`,
                mikrotik.id,
            ),
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setWorking(null),
            },
        );
    };

    const revoke = () => {
        if (
            !confirm(
                'Revoke this WireGuard VPN? The router will lose this tunnel.',
            )
        ) {
            return;
        }

        setWorking('revoke');

        inertiaRouter.delete(
            route(
                'routers.wireguard.revoke',
                mikrotik.id,
            ),
            {
                preserveScroll: true,
                onFinish: () =>
                    setWorking(null),
            },
        );
    };

    const rotate = () => {
        if (
            !confirm(
                'Regenerate WireGuard keys? The old MikroTik command will stop working and you must paste the new command.',
            )
        ) {
            return;
        }

        post('rotate');
    };

    const copyCommand = async () => {
        if (!command) {
            return;
        }

        try {
            await navigator.clipboard.writeText(
                command,
            );

            setCopied(true);

            setTimeout(
                () => setCopied(false),
                1800,
            );
        } catch {
            setCopied(false);
        }
    };

    return (
        <AppLayout title="MikroTik WireGuard VPN">
            <Head title="MikroTik WireGuard VPN" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            WireGuard VPN
                        </h1>

                        <p className="mt-1 text-slate-500">
                            {mikrotik.name} •{' '}
                            {mikrotik.host}:
                            {mikrotik.api_port}
                        </p>
                    </div>

                    <Link
                        href={route(
                            'routers.index',
                        )}
                        className="rounded-lg border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        ← Routers
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 font-semibold text-red-700">
                        {flash.error}
                    </div>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-bold text-slate-800">
                                Management Tunnel
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Only MikroPanel management
                                traffic uses this VPN. Customer
                                internet is not routed through
                                the server.
                            </p>
                        </div>

                        <StatusBadge
                            peer={peer}
                            status={status}
                        />
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Info
                            label="Server Endpoint"
                            value={
                                server?.endpoint ??
                                '-'
                            }
                        />

                        <Info
                            label="Server Tunnel IP"
                            value={
                                server?.tunnel_ip ??
                                '-'
                            }
                        />

                        <Info
                            label="Router Tunnel IP"
                            value={
                                peer?.client_ip ??
                                '-'
                            }
                        />

                        <Info
                            label="Routing"
                            value="Management only"
                        />

                        <Info
                            label="Latest Handshake"
                            value={
                                formatHandshake(
                                    status,
                                )
                            }
                        />

                        <Info
                            label="Remote Endpoint"
                            value={
                                status?.endpoint ??
                                '-'
                            }
                        />

                        <Info
                            label="RX"
                            value={formatBytes(
                                status?.rx_bytes ??
                                    peer?.rx_bytes ??
                                    0,
                            )}
                        />

                        <Info
                            label="TX"
                            value={formatBytes(
                                status?.tx_bytes ??
                                    peer?.tx_bytes ??
                                    0,
                            )}
                        />
                    </div>
                </section>

                {!peer?.active ? (
                    <section className="rounded-2xl border border-violet-200 bg-violet-50 p-6">
                        <h2 className="text-xl font-bold text-violet-900">
                            Create Router VPN
                        </h2>

                        <p className="mt-2 max-w-3xl text-sm text-violet-700">
                            A unique WireGuard key and
                            tunnel IP will be created for
                            this MikroTik. No configuration
                            file is required.
                        </p>

                        <button
                            type="button"
                            disabled={
                                working === 'create'
                            }
                            onClick={() =>
                                post('create')
                            }
                            className="mt-5 rounded-xl bg-violet-600 px-5 py-3 font-bold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {working === 'create'
                                ? 'Creating...'
                                : 'Create VPN'}
                        </button>
                    </section>
                ) : (
                    <>
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-xl font-bold text-slate-800">
                                        MikroTik Command
                                    </h2>

                                    <p className="mt-1 text-sm text-slate-500">
                                        Copy everything below
                                        and paste it once into
                                        the MikroTik Terminal.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    onClick={
                                        copyCommand
                                    }
                                    className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white hover:bg-cyan-700"
                                >
                                    {copied
                                        ? '✓ Copied'
                                        : 'Copy MikroTik Command'}
                                </button>
                            </div>

                            <pre className="mt-5 overflow-x-auto whitespace-pre-wrap break-all rounded-xl bg-slate-950 p-5 text-sm leading-6 text-emerald-300">
                                {command}
                            </pre>

                            <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                This command contains this
                                router's private WireGuard
                                key. Do not share it with
                                another reseller or router.
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow">
                            <h2 className="text-xl font-bold text-slate-800">
                                Connection Actions
                            </h2>

                            <div className="mt-5 flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    onClick={() =>
                                        post('check')
                                    }
                                    disabled={
                                        working ===
                                        'check'
                                    }
                                    className="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {working ===
                                    'check'
                                        ? 'Checking...'
                                        : 'Check Connection'}
                                </button>

                                <button
                                    type="button"
                                    onClick={() =>
                                        post(
                                            'activate-host',
                                        )
                                    }
                                    disabled={
                                        !status?.connected ||
                                        working ===
                                            'activate-host'
                                    }
                                    className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                                >
                                    {working ===
                                    'activate-host'
                                        ? 'Checking API...'
                                        : 'Use VPN as API Host'}
                                </button>

                                <button
                                    type="button"
                                    onClick={rotate}
                                    disabled={
                                        working ===
                                        'rotate'
                                    }
                                    className="rounded-xl bg-amber-500 px-5 py-3 font-bold text-white hover:bg-amber-600 disabled:opacity-50"
                                >
                                    Regenerate VPN
                                </button>

                                <button
                                    type="button"
                                    onClick={revoke}
                                    disabled={
                                        working ===
                                        'revoke'
                                    }
                                    className="rounded-xl bg-red-600 px-5 py-3 font-bold text-white hover:bg-red-700 disabled:opacity-50"
                                >
                                    Revoke VPN
                                </button>
                            </div>

                            <p className="mt-4 text-sm text-slate-500">
                                “Use VPN as API Host” is
                                enabled only after a fresh
                                WireGuard handshake. The
                                panel also checks the
                                MikroTik API TCP port before
                                replacing the router host.
                            </p>
                        </section>
                    </>
                )}

                {status?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
                        {status.error}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function StatusBadge({
    peer,
    status,
}) {
    if (!peer?.active) {
        return (
            <span className="rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-600">
                NOT CREATED
            </span>
        );
    }

    if (status?.connected) {
        return (
            <span className="rounded-full bg-emerald-100 px-4 py-2 text-sm font-bold text-emerald-700">
                ● CONNECTED
            </span>
        );
    }

    return (
        <span className="rounded-full bg-amber-100 px-4 py-2 text-sm font-bold text-amber-700">
            ● WAITING FOR HANDSHAKE
        </span>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-2 break-all font-mono text-sm font-bold text-slate-700">
                {value}
            </div>
        </div>
    );
}

function formatBytes(value) {
    const bytes = Number(value || 0);

    if (!Number.isFinite(bytes)) {
        return '-';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 ** 2) {
        return `${(
            bytes / 1024
        ).toFixed(1)} KiB`;
    }

    if (bytes < 1024 ** 3) {
        return `${(
            bytes /
            1024 ** 2
        ).toFixed(2)} MiB`;
    }

    return `${(
        bytes /
        1024 ** 3
    ).toFixed(2)} GiB`;
}

function formatHandshake(status) {
    if (
        status?.handshake_age_seconds ===
            null ||
        status?.handshake_age_seconds ===
            undefined
    ) {
        return 'Never';
    }

    const seconds = Number(
        status.handshake_age_seconds,
    );

    if (seconds < 60) {
        return `${seconds}s ago`;
    }

    if (seconds < 3600) {
        return `${Math.floor(
            seconds / 60,
        )}m ago`;
    }

    return `${Math.floor(
        seconds / 3600,
    )}h ago`;
}
