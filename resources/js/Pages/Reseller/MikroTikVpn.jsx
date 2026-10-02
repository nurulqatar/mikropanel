import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

export default function MikroTikVpn({
    peers = [],
    server = {},
    flash = {},
}) {
    const {
        data,
        setData,
        post,
        processing,
        reset,
    } = useForm({
        label: '',
    });

    const [working, setWorking] =
        useState(null);

    const [copied, setCopied] =
        useState(null);

    const createVpn = (event) => {
        event.preventDefault();

        post(
            route(
                'reseller.mikrotik-vpn.store',
            ),
            {
                preserveScroll: true,
                onSuccess: () =>
                    reset('label'),
            },
        );
    };

    const run = (
        action,
        peerId,
    ) => {
        const key =
            `${action}-${peerId}`;

        setWorking(key);

        if (action === 'revoke') {
            if (
                !confirm(
                    'Revoke this VPN?',
                )
            ) {
                setWorking(null);
                return;
            }

            router.delete(
                route(
                    'reseller.mikrotik-vpn.revoke',
                    peerId,
                ),
                {
                    preserveScroll: true,
                    onFinish: () =>
                        setWorking(null),
                },
            );

            return;
        }

        if (
            action === 'rotate'
            && !confirm(
                'Regenerate VPN keys? You must paste the NEW command into MikroTik.',
            )
        ) {
            setWorking(null);
            return;
        }

        router.post(
            route(
                `reseller.mikrotik-vpn.${action}`,
                peerId,
            ),
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setWorking(null),
            },
        );
    };

    const copy = async (
        key,
        text,
    ) => {
        await navigator.clipboard
            .writeText(text);

        setCopied(key);

        setTimeout(
            () => setCopied(null),
            1600,
        );
    };

    return (
        <AppLayout title="MikroTik VPN">
            <Head title="MikroTik VPN" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black text-slate-800">
                        MikroTik VPN
                    </h1>

                    <p className="mt-2 max-w-4xl text-slate-500">
                        Create the VPN first.
                        Paste the generated command
                        into MikroTik, then add the
                        router to MikroPanel using the
                        displayed MikroTik VPN Local IP.
                    </p>
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

                <section className="rounded-2xl border border-violet-200 bg-violet-50 p-6">
                    <h2 className="text-xl font-black text-violet-900">
                        Create New MikroTik VPN
                    </h2>

                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <Info
                            label="MikroPanel VPN IP"
                            value={
                                server.tunnel_ip
                                ?? '-'
                            }
                        />

                        <Info
                            label="Public Endpoint"
                            value={
                                server.endpoint
                                ?? '-'
                            }
                        />

                        <Info
                            label="Router IP Pool"
                            value={
                                server.pool
                                ?? '-'
                            }
                        />
                    </div>

                    <form
                        onSubmit={createVpn}
                        className="mt-5 flex flex-wrap gap-3"
                    >
                        <input
                            value={data.label}
                            onChange={(e) =>
                                setData(
                                    'label',
                                    e.target.value,
                                )
                            }
                            placeholder="Router / Site name (optional)"
                            className="min-w-[260px] flex-1 rounded-xl border-slate-300"
                        />

                        <button
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-6 py-3 font-black text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Creating...'
                                : '+ Create VPN'}
                        </button>
                    </form>
                </section>

                {peers.length === 0 ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow">
                        <div className="text-5xl">
                            🔐
                        </div>

                        <h2 className="mt-4 text-xl font-black">
                            No VPN Created Yet
                        </h2>

                        <p className="mt-2 text-slate-500">
                            Create the VPN before adding
                            your MikroTik router.
                        </p>
                    </section>
                ) : (
                    <div className="space-y-6">
                        {peers.map(
                            ({
                                peer,
                                status,
                                command,
                            }) => (
                                <VpnCard
                                    key={peer.id}
                                    peer={peer}
                                    status={status}
                                    command={command}
                                    working={working}
                                    copied={copied}
                                    run={run}
                                    copy={copy}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}


function VpnCard({
    peer,
    status,
    command,
    working,
    copied,
    run,
    copy,
}) {
    const connected =
        Boolean(
            status?.connected,
        );

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 p-6">
                <div>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-black text-slate-800">
                            {peer.label
                                || `VPN #${peer.id}`}
                        </h2>

                        <span
                            className={`rounded-full px-3 py-1 text-xs font-black ${
                                !peer.active
                                    ? 'bg-red-100 text-red-700'
                                    : connected
                                      ? 'bg-emerald-100 text-emerald-700'
                                      : 'bg-amber-100 text-amber-700'
                            }`}
                        >
                            {!peer.active
                                ? 'REVOKED'
                                : connected
                                  ? 'CONNECTED'
                                  : 'WAITING'}
                        </span>
                    </div>

                    {peer.router_id ? (
                        <p className="mt-2 text-sm font-semibold text-emerald-700">
                            Linked Router:{' '}
                            {peer.router_name
                                || `#${peer.router_id}`}
                        </p>
                    ) : (
                        <p className="mt-2 text-sm font-semibold text-violet-700">
                            Router not added yet
                        </p>
                    )}
                </div>
            </div>

            <div className="p-6">
                <div className="rounded-2xl border-2 border-cyan-300 bg-cyan-50 p-5">
                    <div className="text-xs font-black uppercase tracking-widest text-cyan-700">
                        MikroTik VPN Local IP
                    </div>

                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <div className="font-mono text-3xl font-black text-cyan-950">
                            {peer.client_ip}
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                copy(
                                    `ip-${peer.id}`,
                                    peer.client_ip,
                                )
                            }
                            className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-black text-white"
                        >
                            {copied ===
                            `ip-${peer.id}`
                                ? '✓ Copied'
                                : 'Copy IP'}
                        </button>
                    </div>

                    <p className="mt-3 text-sm font-semibold text-cyan-800">
                        Use this exact IP in
                        Add Router → Host/IP.
                        You do not need to search
                        inside MikroTik for this IP.
                    </p>
                </div>

                <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Info
                        label="Latest Handshake"
                        value={
                            status
                                ?.handshake_age_seconds
                                !== null
                                && status
                                    ?.handshake_age_seconds
                                    !== undefined
                                ? `${status.handshake_age_seconds}s ago`
                                : 'Never'
                        }
                    />

                    <Info
                        label="Remote Endpoint"
                        value={
                            status?.endpoint
                            || '-'
                        }
                    />

                    <Info
                        label="RX"
                        value={bytes(
                            status?.rx_bytes
                            || 0,
                        )}
                    />

                    <Info
                        label="TX"
                        value={bytes(
                            status?.tx_bytes
                            || 0,
                        )}
                    />
                </div>

                {peer.active && command && (
                    <div className="mt-6">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 className="font-black text-slate-800">
                                    MikroTik Command
                                </h3>

                                <p className="mt-1 text-sm text-slate-500">
                                    Paste the whole command
                                    into MikroTik Terminal.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    copy(
                                        `cmd-${peer.id}`,
                                        command,
                                    )
                                }
                                className="rounded-xl bg-slate-900 px-5 py-3 font-black text-white"
                            >
                                {copied ===
                                `cmd-${peer.id}`
                                    ? '✓ Copied'
                                    : 'Copy MikroTik Command'}
                            </button>
                        </div>

                        <pre className="mt-4 max-h-[320px] overflow-auto whitespace-pre-wrap break-all rounded-xl bg-slate-950 p-5 text-sm leading-6 text-emerald-300">
                            {command}
                        </pre>
                    </div>
                )}

                <div className="mt-6 flex flex-wrap gap-3">
                    {peer.active && (
                        <>
                            <button
                                type="button"
                                disabled={
                                    working ===
                                    `check-${peer.id}`
                                }
                                onClick={() =>
                                    run(
                                        'check',
                                        peer.id,
                                    )
                                }
                                className="rounded-xl bg-emerald-600 px-5 py-3 font-black text-white disabled:opacity-50"
                            >
                                Check Connection
                            </button>

                            <button
                                type="button"
                                disabled={
                                    working ===
                                    `rotate-${peer.id}`
                                }
                                onClick={() =>
                                    run(
                                        'rotate',
                                        peer.id,
                                    )
                                }
                                className="rounded-xl bg-amber-500 px-5 py-3 font-black text-white disabled:opacity-50"
                            >
                                Regenerate VPN
                            </button>

                            <button
                                type="button"
                                disabled={
                                    working ===
                                    `revoke-${peer.id}`
                                }
                                onClick={() =>
                                    run(
                                        'revoke',
                                        peer.id,
                                    )
                                }
                                className="rounded-xl bg-red-600 px-5 py-3 font-black text-white disabled:opacity-50"
                            >
                                Revoke VPN
                            </button>
                        </>
                    )}

                    {peer.active
                        && connected
                        && !peer.router_id && (
                            <Link
                                href={route(
                                    'routers.create',
                                )}
                                className="rounded-xl bg-cyan-600 px-5 py-3 font-black text-white"
                            >
                                + Add Router
                            </Link>
                        )}
                </div>
            </div>
        </section>
    );
}


function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-2 break-all font-mono text-sm font-bold text-slate-700">
                {value}
            </div>
        </div>
    );
}


function bytes(value) {
    const n =
        Number(value || 0);

    if (n < 1024) {
        return `${n} B`;
    }

    if (n < 1024 ** 2) {
        return `${(
            n / 1024
        ).toFixed(1)} KiB`;
    }

    return `${(
        n / 1024 ** 2
    ).toFixed(2)} MiB`;
}
