import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
} from '@inertiajs/react';

export default function Sessions({
    sessions = [],
    capabilities = {},
}) {
    return (
        <AppLayout title="Hotspot Live Sessions">
            <Head title="Hotspot Live Sessions" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Live Sessions
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Current users connected through
                        MikroTik Hotspot.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>User</Th>
                                <Th>Server</Th>
                                <Th>IP</Th>
                                <Th>MAC</Th>
                                <Th>Login By</Th>
                                <Th>Uptime</Th>
                                <Th>Upload</Th>
                                <Th>Download</Th>
                                <Th>Last Seen</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {sessions.map(
                                (session) => (
                                    <tr
                                        key={
                                            session.id
                                        }
                                    >
                                        <Td>
                                            <strong>
                                                {
                                                    session.username
                                                }
                                            </strong>
                                        </Td>

                                        <Td>
                                            {session.server?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {session.address ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {session.mac_address ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {session.login_by ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {duration(
                                                session.uptime_seconds,
                                            )}
                                        </Td>

                                        <Td>
                                            {bytes(
                                                session.bytes_in,
                                            )}
                                        </Td>

                                        <Td>
                                            {bytes(
                                                session.bytes_out,
                                            )}
                                        </Td>

                                        <Td>
                                            {session.last_seen_at ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {capabilities.manage ? (
                                                <button
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                `Disconnect ${session.username}?`,
                                                            )
                                                        ) {
                                                            router.post(
                                                                route(
                                                                    'hotspot.sessions.disconnect',
                                                                    session.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className="rounded bg-red-600 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    Disconnect
                                                </button>
                                            ) : (
                                                '-'
                                            )}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {sessions.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="10"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Hotspot user online.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function duration(value) {
    let total =
        Number(value ?? 0);

    const hours =
        Math.floor(
            total / 3600,
        );

    total %= 3600;

    const minutes =
        Math.floor(
            total / 60,
        );

    return `${hours}h ${minutes}m`;
}

function bytes(value) {
    let amount =
        Number(value ?? 0);

    if (amount < 1024) {
        return `${amount} B`;
    }

    if (
        amount <
        1024 * 1024
    ) {
        return `${(
            amount / 1024
        ).toFixed(1)} KB`;
    }

    if (
        amount <
        1024 * 1024 * 1024
    ) {
        return `${(
            amount /
            1024 /
            1024
        ).toFixed(1)} MB`;
    }

    return `${(
        amount /
        1024 /
        1024 /
        1024
    ).toFixed(2)} GB`;
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
        <td className="whitespace-nowrap px-5 py-4 text-sm">
            {children}
        </td>
    );
}
