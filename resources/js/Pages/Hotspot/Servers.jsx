import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
} from '@inertiajs/react';

export default function Servers({
    servers = [],
    capabilities = {},
}) {
    return (
        <AppLayout title="Hotspot Servers">
            <Head title="Hotspot Servers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Hotspot Servers
                        </h1>

                        <p className="mt-1 text-slate-500">
                            MikroTik Hotspot server
                            discovery and synchronization.
                        </p>
                    </div>

                    {capabilities.manage && (
                        <button
                            onClick={() =>
                                router.post(
                                    route(
                                        'hotspot.discover',
                                    ),
                                    {},
                                    {
                                        preserveScroll:
                                            true,
                                    },
                                )
                            }
                            className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white"
                        >
                            Discover Servers
                        </button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Server</Th>
                                <Th>Router</Th>
                                <Th>Interface</Th>
                                <Th>Pool</Th>
                                <Th>Profile</Th>
                                <Th>DNS</Th>
                                <Th>Users</Th>
                                <Th>Online</Th>
                                <Th>Status</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {servers.map(
                                (server) => (
                                    <tr
                                        key={
                                            server.id
                                        }
                                    >
                                        <Td>
                                            <strong>
                                                {
                                                    server.name
                                                }
                                            </strong>
                                        </Td>

                                        <Td>
                                            {server.router?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {server.interface ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {server.address_pool ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {server.hotspot_profile ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {server.dns_name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {server.users_count ??
                                                0}
                                        </Td>

                                        <Td>
                                            {server.active_sessions_count ??
                                                0}
                                        </Td>

                                        <Td>
                                            <Badge
                                                online={
                                                    server.connected
                                                }
                                            />
                                        </Td>

                                        <Td>
                                            {capabilities.manage ? (
                                                <button
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'hotspot.servers.sync',
                                                                server.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                    className="rounded-lg bg-slate-700 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    Sync
                                                </button>
                                            ) : (
                                                '-'
                                            )}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {servers.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="10"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Hotspot server found.
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

function Badge({ online }) {
    return (
        <span
            className={`rounded-full px-2.5 py-1 text-xs font-bold ${
                online
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {online
                ? 'Connected'
                : 'Offline'}
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
